const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/public-calendar.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function makeFakeDayPilotDate(input) {
  let source = input;
  if (source && typeof source === 'object' && source._date instanceof Date) {
    source = source._date;
  }

  const date = source instanceof Date ? new Date(source) : new Date(source || '2026-05-01T00:00:00');

  function clone(nextDate) {
    return makeFakeDayPilotDate(nextDate);
  }

  return {
    _date: date,
    addDays(days) {
      const next = new Date(this._date);
      next.setDate(next.getDate() + days);
      return clone(next);
    },
    addMonths(months) {
      const next = new Date(this._date);
      next.setMonth(next.getMonth() + months);
      return clone(next);
    },
    firstDayOfMonth() {
      const next = new Date(this._date);
      next.setDate(1);
      return clone(next);
    },
    getDatePart() {
      const next = new Date(this._date);
      next.setHours(0, 0, 0, 0);
      return clone(next);
    },
    toString(format) {
      const yyyy = this._date.getFullYear();
      const mm = String(this._date.getMonth() + 1).padStart(2, '0');
      const dd = String(this._date.getDate()).padStart(2, '0');
      if (format === 'yyyy-MM-dd') {
        return `${yyyy}-${mm}-${dd}`;
      }
      return `${dd}/${mm}/${yyyy}`;
    }
  };
}

function installDayPilotStub() {
  let latestView = null;

  function createView(config) {
    latestView = {
      startDate: config.startDate,
      events: { list: [] },
      init: jest.fn(),
      update: jest.fn(),
      dispose: jest.fn(),
      visibleStart: () => makeFakeDayPilotDate('2026-05-01T00:00:00'),
      visibleEnd: () => makeFakeDayPilotDate('2026-06-01T00:00:00')
    };

    return latestView;
  }

  window.DayPilot = {
    Date: function(input) {
      return makeFakeDayPilotDate(input);
    },
    Month: function(id, config) {
      return createView(config || {});
    },
    Calendar: function(id, config) {
      return createView(config || {});
    },
    Scheduler: function(id, config) {
      return createView(config || {});
    },
    Navigator: function(id, options) {
      return {
        selectMode: options.selectMode,
        init: jest.fn(),
        select: jest.fn(),
        update: jest.fn()
      };
    }
  };

  window.DayPilot.Date.today = () => makeFakeDayPilotDate('2026-05-01T00:00:00');

  return {
    getLatestView: () => latestView
  };
}

describe('public-calendar.js', () => {
  let dayPilotState;

  beforeEach(() => {
    jest.restoreAllMocks();
    window.localStorage.clear();

    document.body.innerHTML = `
      <div class="myvh-public-calendar-wrap">
        <div class="myvh-cal-venue-filter" style="display:none">
          <select class="myvh-cal-venue-select"></select>
        </div>
        <div class="myvh-cal-key">
          <div class="myvh-cal-key-status-items"></div>
          <div class="myvh-cal-key-room-items"></div>
        </div>
        <div class="myvh-cal-room-filter"></div>
        <div class="myvh-cal-toolbar">
          <button class="myvh-mode-btn" data-mode="calendar">Calendar</button>
          <button class="myvh-mode-btn" data-mode="scheduler">Scheduler</button>
          <button class="myvh-detail-btn" data-view="month">Month</button>
          <button class="myvh-cal-today">Today</button>
        </div>
        <div class="myvh-cal-title"></div>
        <div id="public-calendar"></div>
        <div id="public-calendar-nav"></div>
      </div>
    `;

    dayPilotState = installDayPilotStub();

    window.fetch = jest.fn().mockResolvedValue({
      json: () => Promise.resolve([])
    });

    window.ensureThreeLetterEnglishWeekdays = jest.fn();

    window.myvhCalConfig = {
      containerId: 'public-calendar',
      navContainerId: 'public-calendar-nav',
      eventsUrl: '/events',
      nonce: 'nonce-1',
      view: 'month',
      mode: 'calendar',
      rooms: [
        { id: 1, name: 'Main Hall', venueId: 101, venue: 'Venue A', roomColour: '#2271b1' },
        { id: 2, name: 'Side Room', venueId: 102, venue: 'Venue B', roomColour: '#2d8f45' }
      ],
      statusColors: {
        confirmed: '#2271b1',
        pending: '#f0a500'
      }
    };

    global.myvhCalConfig = window.myvhCalConfig;
  });

  test('initialises venue selector from persisted venue id', async () => {
    window.localStorage.setItem('myvhCalendarVenue_public', '102');

    window.eval(scriptSource);
    document.dispatchEvent(new Event('DOMContentLoaded'));

    await flushPromises();
    await flushPromises();

    const venueWrap = document.querySelector('.myvh-cal-venue-filter');
    const venueSelect = document.querySelector('.myvh-cal-venue-select');

    expect(venueWrap.style.display).toBe('');
    expect(venueSelect.value).toBe('102');
    expect(venueSelect.querySelectorAll('option')).toHaveLength(2);
  });

  test('updates storage and reloads events when venue selection changes', async () => {
    window.eval(scriptSource);
    document.dispatchEvent(new Event('DOMContentLoaded'));

    await flushPromises();
    await flushPromises();

    const initialFetchCalls = window.fetch.mock.calls.length;

    const venueSelect = document.querySelector('.myvh-cal-venue-select');
    venueSelect.value = '102';
    venueSelect.dispatchEvent(new Event('change', { bubbles: true }));

    await flushPromises();
    await flushPromises();

    expect(window.localStorage.getItem('myvhCalendarVenue_public')).toBe('102');
    expect(window.fetch.mock.calls.length).toBeGreaterThan(initialFetchCalls);
  });

  test('hides room filter in scheduler mode and shows in calendar mode', async () => {
    window.myvhCalConfig.rooms = [
      { id: 1, name: 'Main Hall', venueId: 101, venue: 'Venue A', roomColour: '#2271b1' },
      { id: 3, name: 'Small Hall', venueId: 101, venue: 'Venue A', roomColour: '#2d8f45' }
    ];
    global.myvhCalConfig = window.myvhCalConfig;

    window.eval(scriptSource);
    document.dispatchEvent(new Event('DOMContentLoaded'));

    await flushPromises();
    await flushPromises();

    const roomFilter = document.querySelector('.myvh-cal-room-filter');

    expect(roomFilter.style.display).toBe('');

    document.querySelector('.myvh-mode-btn[data-mode="scheduler"]').click();
    await flushPromises();
    expect(roomFilter.style.display).toBe('none');

    document.querySelector('.myvh-mode-btn[data-mode="calendar"]').click();
    await flushPromises();
    expect(roomFilter.style.display).toBe('');
  });

  test('renders status and room legend entries', async () => {
    window.eval(scriptSource);
    document.dispatchEvent(new Event('DOMContentLoaded'));

    await flushPromises();
    await flushPromises();

    const statusItems = document.querySelectorAll('.myvh-cal-key-status-items .myvh-cal-key-item');
    const roomItems = document.querySelectorAll('.myvh-cal-key-room-items .myvh-cal-key-item');

    expect(statusItems.length).toBeGreaterThan(0);
    expect(roomItems.length).toBeGreaterThan(0);
    expect(document.querySelector('.myvh-cal-key-room-items').textContent).toContain('Main Hall');
  });

  test('logs error when events endpoint returns malformed payload', async () => {
    const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    window.fetch.mockResolvedValueOnce({
      json: () => Promise.resolve({ invalid: true })
    });

    window.eval(scriptSource);
    document.dispatchEvent(new Event('DOMContentLoaded'));

    await flushPromises();
    await flushPromises();

    expect(errorSpy).toHaveBeenCalled();
    errorSpy.mockRestore();
  });

  test('ignores stale event response when a newer load finishes first', async () => {
    const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    let resolveFirst;
    let resolveSecond;

    window.fetch
      .mockReturnValueOnce(new Promise((resolve) => {
        resolveFirst = resolve;
      }))
      .mockReturnValueOnce(new Promise((resolve) => {
        resolveSecond = resolve;
      }));

    window.eval(scriptSource);
    document.dispatchEvent(new Event('DOMContentLoaded'));

    await flushPromises();

    const venueSelect = document.querySelector('.myvh-cal-venue-select');
    venueSelect.value = '102';
    venueSelect.dispatchEvent(new Event('change', { bubbles: true }));

    expect(window.fetch.mock.calls.length).toBeGreaterThanOrEqual(2);

    resolveSecond({
      json: () => Promise.resolve([
        { id: 'newer', start: '2026-05-02T09:00:00', end: '2026-05-02T10:00:00', text: 'Newer', tags: { status: 'confirmed', roomId: 2 }, resource: 2 }
      ])
    });
    await flushPromises();
    await flushPromises();

    resolveFirst({
      json: () => Promise.resolve({ invalid: true })
    });
    await flushPromises();
    await flushPromises();

    expect(errorSpy).not.toHaveBeenCalled();
    errorSpy.mockRestore();
  });
});
