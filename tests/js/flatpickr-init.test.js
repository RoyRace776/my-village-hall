const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/flatpickr-init.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

describe('MyvhFlatpickr', () => {
  beforeEach(() => {
    jest.restoreAllMocks();
    delete window.MyvhFlatpickr;

    window.flatpickr = jest.fn((input) => {
      const instance = {
        altInput: document.createElement('input'),
        set: jest.fn(),
        setDate: jest.fn(),
        close: jest.fn()
      };
      input._flatpickr = instance;
      return instance;
    });

    window.eval(scriptSource);
  });

  test('initializes time picker with correct defaults', () => {
    const input = document.createElement('input');
    input.dataset.myvhPicker = 'time';
    input.dataset.myvhMinuteIncrement = '10';
    input.value = '09:30';

    const instance = window.MyvhFlatpickr.initInput(input, {});

    expect(window.flatpickr).toHaveBeenCalledTimes(1);
    const config = window.flatpickr.mock.calls[0][1];
    expect(config.enableTime).toBe(true);
    expect(config.noCalendar).toBe(true);
    expect(config.minuteIncrement).toBe(10);
    expect(config.dateFormat).toBe('H:i');
    expect(instance.set).toHaveBeenCalled();
  });

  test('setValue uses setDate with custom format when flatpickr exists', () => {
    const input = document.createElement('input');
    input.dataset.myvhPicker = 'date';
    input.dataset.myvhFormat = 'd/m/Y';
    input._flatpickr = {
      setDate: jest.fn()
    };

    window.MyvhFlatpickr.setValue(input, '02/05/2026');

    expect(input._flatpickr.setDate).toHaveBeenCalledWith('02/05/2026', false, 'd/m/Y');
  });

  test('syncState disables alt input and closes picker when input disabled', () => {
    const input = document.createElement('input');
    input.disabled = true;
    input.dataset.myvhAllowInput = '0';

    const close = jest.fn();
    const set = jest.fn();
    const altInput = document.createElement('input');

    input._flatpickr = {
      set,
      close,
      altInput
    };

    window.MyvhFlatpickr.syncState(input);

    expect(set).toHaveBeenCalledWith('clickOpens', false);
    expect(altInput.disabled).toBe(true);
    expect(altInput.readOnly).toBe(true);
    expect(close).toHaveBeenCalled();
  });

  test('initWithin applies selector specific options', () => {
    document.body.innerHTML = `
      <input id="date1" data-myvh-picker="date" class="date-field" />
      <input id="time1" data-myvh-picker="time" class="time-field" />
    `;

    window.MyvhFlatpickr.initWithin(document, {
      '.date-field': { altInput: false },
      '.time-field': { allowInput: false }
    });

    expect(window.flatpickr).toHaveBeenCalledTimes(2);

    const dateConfig = window.flatpickr.mock.calls[0][1];
    const timeConfig = window.flatpickr.mock.calls[1][1];

    expect(dateConfig.altInput).toBe(true);
    expect(timeConfig.allowInput).toBe(false);
  });
});
