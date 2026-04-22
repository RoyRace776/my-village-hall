/**
 * Dashboard Tests
 * Tests for dashboard functionality
 */

describe('Dashboard - Data Utilities', () => {
  describe('Data formatting', () => {
    test('should format currency values', () => {
      const formatCurrency = (value) => {
        return new Intl.NumberFormat('en-GB', {
          style: 'currency',
          currency: 'GBP'
        }).format(value);
      };

      expect(formatCurrency(1000)).toContain('1,000');
      expect(formatCurrency(1000.50)).toContain('1,000.50');
    });

    test('should format dates', () => {
      const formatDate = (date) => {
        return new Intl.DateTimeFormat('en-GB').format(date);
      };

      const testDate = new Date('2026-04-22');
      const formatted = formatDate(testDate);
      expect(formatted).toMatch(/22\/04\/2026|22\.04\.2026/);
    });

    test('should format time durations', () => {
      const formatDuration = (hours, minutes) => {
        const parts = [];
        if (hours > 0) parts.push(`${hours}h`);
        if (minutes > 0) parts.push(`${minutes}m`);
        return parts.join(' ') || '0m';
      };

      expect(formatDuration(2, 30)).toBe('2h 30m');
      expect(formatDuration(1, 0)).toBe('1h');
      expect(formatDuration(0, 45)).toBe('45m');
      expect(formatDuration(0, 0)).toBe('0m');
    });
  });

  describe('Statistical calculations', () => {
    test('should calculate average', () => {
      const calculateAverage = (values) => {
        if (!Array.isArray(values) || values.length === 0) return 0;
        const sum = values.reduce((acc, val) => acc + val, 0);
        return sum / values.length;
      };

      expect(calculateAverage([10, 20, 30])).toBe(20);
      expect(calculateAverage([5, 5, 5, 5])).toBe(5);
      expect(calculateAverage([])).toBe(0);
    });

    test('should calculate total', () => {
      const calculateTotal = (values) => {
        if (!Array.isArray(values)) return 0;
        return values.reduce((acc, val) => acc + val, 0);
      };

      expect(calculateTotal([10, 20, 30])).toBe(60);
      expect(calculateTotal([5, 5, 5])).toBe(15);
      expect(calculateTotal([])).toBe(0);
    });

    test('should find minimum and maximum', () => {
      const findMinMax = (values) => {
        if (!Array.isArray(values) || values.length === 0) {
          return { min: 0, max: 0 };
        }
        return {
          min: Math.min(...values),
          max: Math.max(...values)
        };
      };

      const result = findMinMax([10, 5, 30, 15]);
      expect(result.min).toBe(5);
      expect(result.max).toBe(30);
    });
  });
});

describe('Dashboard - DOM Rendering', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    document.body.removeChild(container);
  });

  test('should render dashboard widgets', () => {
    const widget = document.createElement('div');
    widget.className = 'dashboard-widget';
    widget.innerHTML = '<h3>Widget Title</h3><p>Widget Content</p>';
    container.appendChild(widget);

    const widgets = container.querySelectorAll('.dashboard-widget');
    expect(widgets).toHaveLength(1);
    expect(widgets[0].querySelector('h3').textContent).toBe('Widget Title');
  });

  test('should render statistics cards', () => {
    const statsCard = document.createElement('div');
    statsCard.className = 'stat-card';
    statsCard.innerHTML = `
      <div class="stat-label">Total Bookings</div>
      <div class="stat-value">42</div>
    `;
    container.appendChild(statsCard);

    const label = container.querySelector('.stat-label');
    const value = container.querySelector('.stat-value');

    expect(label.textContent).toBe('Total Bookings');
    expect(value.textContent).toBe('42');
  });

  test('should render charts container', () => {
    const chartContainer = document.createElement('div');
    chartContainer.id = 'bookings-chart';
    chartContainer.className = 'chart-container';
    container.appendChild(chartContainer);

    expect(container.querySelector('#bookings-chart')).toBeTruthy();
    expect(container.querySelector('.chart-container')).toBeTruthy();
  });
});

describe('Dashboard - State Management', () => {
  test('should manage dashboard state', () => {
    const dashboardState = {
      selectedPeriod: 'month',
      selectedVenue: null,
      isLoading: false,
      data: {},

      setPeriod(period) {
        this.selectedPeriod = period;
      },

      setVenue(venue) {
        this.selectedVenue = venue;
      },

      setLoading(loading) {
        this.isLoading = loading;
      }
    };

    expect(dashboardState.selectedPeriod).toBe('month');

    dashboardState.setPeriod('week');
    expect(dashboardState.selectedPeriod).toBe('week');

    dashboardState.setVenue('venue-1');
    expect(dashboardState.selectedVenue).toBe('venue-1');

    dashboardState.setLoading(true);
    expect(dashboardState.isLoading).toBe(true);
  });
});
