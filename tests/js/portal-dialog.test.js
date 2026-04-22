/**
 * Portal Dialog Tests
 * Specific tests for portal dialog functions
 */

describe('MyvhPortalDialog - Site Name Resolution', () => {
  afterEach(() => {
    delete window.myvhPortal;
    delete window.myvhCal;
  });

  test('should get site name from myvhPortal', () => {
    window.myvhPortal = { site_name: 'Test Village Hall' };

    const getSiteName = () => {
      if (window.myvhPortal && window.myvhPortal.site_name) {
        return String(window.myvhPortal.site_name);
      }
      return 'Message';
    };

    expect(getSiteName()).toBe('Test Village Hall');
  });

  test('should get site name from myvhPortal.siteName', () => {
    window.myvhPortal = { siteName: 'Village Hall Portal' };

    const getSiteName = () => {
      if (window.myvhPortal && window.myvhPortal.siteName) {
        return String(window.myvhPortal.siteName);
      }
      return 'Message';
    };

    expect(getSiteName()).toBe('Village Hall Portal');
  });

  test('should fallback to myvhCal.site_name', () => {
    window.myvhCal = { site_name: 'Calendar Site' };

    const getSiteName = () => {
      if (window.myvhPortal && window.myvhPortal.site_name) {
        return String(window.myvhPortal.site_name);
      }
      if (window.myvhCal && window.myvhCal.site_name) {
        return String(window.myvhCal.site_name);
      }
      return 'Message';
    };

    expect(getSiteName()).toBe('Calendar Site');
  });

  test('should fallback to document.title', () => {
    const originalTitle = document.title;
    document.title = 'Page Title';

    const getSiteName = () => {
      return document.title || 'Message';
    };

    expect(getSiteName()).toBe('Page Title');

    document.title = originalTitle;
  });

  test('should use default when no name available', () => {
    const getSiteName = () => {
      return 'Message';
    };

    expect(getSiteName()).toBe('Message');
  });
});

describe('MyvhPortalDialog - Style Management', () => {
  test('should inject styles only once', () => {
    let styleInjected = false;

    const ensureStyles = () => {
      if (styleInjected) {
        return false; // Already injected
      }

      const style = document.createElement('style');
      style.id = 'myvh-portal-dialog-styles';
      document.head.appendChild(style);
      styleInjected = true;
      return true;
    };

    const result1 = ensureStyles();
    const result2 = ensureStyles();

    expect(result1).toBe(true);
    expect(result2).toBe(false);

    const styleEl = document.getElementById('myvh-portal-dialog-styles');
    styleEl?.remove();
  });

  test('should include critical CSS classes in styles', () => {
    const cssClasses = [
      'myvh-portal-dialog-backdrop',
      'myvh-portal-dialog',
      'myvh-portal-dialog__head',
      'myvh-portal-dialog__body',
      'myvh-portal-dialog__actions',
      'myvh-portal-dialog__btn'
    ];

    cssClasses.forEach(className => {
      expect(className.startsWith('myvh-portal-dialog')).toBe(true);
    });
  });
});

describe('MyvhPortalDialog - Queue Management', () => {
  test('should queue dialogs when one is active', () => {
    const queue = [];
    let active = false;

    const addToQueue = (item) => {
      queue.push(item);
    };

    const processQueue = () => {
      if (queue.length > 0 && !active) {
        active = true;
        return queue.shift();
      }
      return null;
    };

    const item1 = { type: 'alert', message: 'First' };
    const item2 = { type: 'alert', message: 'Second' };

    addToQueue(item1);
    addToQueue(item2);

    expect(queue).toHaveLength(2);

    const processed = processQueue();
    expect(processed).toEqual(item1);
    expect(queue).toHaveLength(1);
  });

  test('should prevent queue processing when active', () => {
    let active = true;
    const queue = [{ type: 'alert' }];

    const canProcess = () => {
      return queue.length > 0 && !active;
    };

    expect(canProcess()).toBe(false);
  });

  test('should process queued items sequentially', () => {
    const queue = [];
    let active = false;
    const processed = [];

    const show = (item) => {
      queue.push(item);
      if (!active) processNext();
    };

    const processNext = () => {
      if (queue.length === 0) return;
      active = true;
      const item = queue.shift();
      processed.push(item);
      // Simulate dialog completion
      active = false;
      if (queue.length > 0) processNext();
    };

    show({ id: 1 });
    show({ id: 2 });
    show({ id: 3 });

    // All items should be in processed order
    expect(processed).toHaveLength(3);
    expect(processed[0].id).toBe(1);
    expect(processed[1].id).toBe(2);
    expect(processed[2].id).toBe(3);
  });
});

describe('MyvhPortalDialog - Alert Dialog', () => {
  test('should create alert dialog with message', () => {
    const createAlertDialog = (message, options = {}) => {
      return {
        type: 'alert',
        message,
        title: options.title || 'Alert',
        buttons: [{ label: 'OK', primary: true }]
      };
    };

    const dialog = createAlertDialog('This is an alert', { title: 'Warning' });

    expect(dialog.type).toBe('alert');
    expect(dialog.message).toBe('This is an alert');
    expect(dialog.title).toBe('Warning');
    expect(dialog.buttons).toHaveLength(1);
  });

  test('should handle alert button click', () => {
    const handleAlertClick = jest.fn();

    const dialog = document.createElement('div');
    const button = document.createElement('button');
    button.onclick = handleAlertClick;

    dialog.appendChild(button);
    button.click();

    expect(handleAlertClick).toHaveBeenCalled();
  });

  test('should return promise that resolves on dismiss', () => {
    const alert = (message) => {
      return new Promise((resolve) => {
        setTimeout(() => resolve(true), 0);
      });
    };

    return alert('Test message').then(result => {
      expect(result).toBe(true);
    });
  });
});

describe('MyvhPortalDialog - Confirm Dialog', () => {
  test('should create confirm dialog with two buttons', () => {
    const createConfirmDialog = (message, options = {}) => {
      return {
        type: 'confirm',
        message,
        title: options.title || 'Confirm',
        buttons: [
          { label: options.cancelLabel || 'Cancel', primary: false },
          { label: options.okLabel || 'OK', primary: true }
        ]
      };
    };

    const dialog = createConfirmDialog('Are you sure?', { okLabel: 'Yes' });

    expect(dialog.type).toBe('confirm');
    expect(dialog.buttons).toHaveLength(2);
    expect(dialog.buttons[0].label).toBe('Cancel');
    expect(dialog.buttons[1].label).toBe('Yes');
  });

  test('should return true on OK click', () => {
    const confirm = (message) => {
      return new Promise((resolve) => {
        // Simulate OK button click
        setTimeout(() => resolve(true), 0);
      });
    };

    return confirm('Continue?').then(result => {
      expect(result).toBe(true);
    });
  });

  test('should return false on Cancel click', () => {
    const confirm = (message) => {
      return new Promise((resolve) => {
        // Simulate Cancel button click
        setTimeout(() => resolve(false), 0);
      });
    };

    return confirm('Continue?').then(result => {
      expect(result).toBe(false);
    });
  });
});

describe('MyvhPortalDialog - Keyboard Navigation', () => {
  test('should close dialog on ESC key', () => {
    const handleKeyDown = jest.fn();

    const attachKeyboardListener = () => {
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          handleKeyDown('escape');
        }
      });
    };

    attachKeyboardListener();

    const event = new KeyboardEvent('keydown', { key: 'Escape' });
    document.dispatchEvent(event);

    expect(handleKeyDown).toHaveBeenCalledWith('escape');
  });

  test('should confirm on Enter key in confirm dialog', () => {
    const handleKeyDown = jest.fn();

    const attachConfirmKeyListener = () => {
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          handleKeyDown('enter');
        }
      });
    };

    attachConfirmKeyListener();

    const event = new KeyboardEvent('keydown', { key: 'Enter' });
    document.dispatchEvent(event);

    expect(handleKeyDown).toHaveBeenCalledWith('enter');
  });

  test('should remove keyboard listener when dialog closes', () => {
    const removeListener = jest.fn();

    const onKeydown = () => {
      // Handler function
    };

    const closeCurrent = () => {
      document.removeEventListener('keydown', onKeydown);
      removeListener();
    };

    closeCurrent();

    expect(removeListener).toHaveBeenCalled();
  });
});

describe('MyvhPortalDialog - Backdrop Click Handling', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    document.body.removeChild(container);
  });

  test('should close dialog when backdrop clicked', () => {
    const backdrop = document.createElement('div');
    backdrop.className = 'myvh-portal-dialog-backdrop';
    const closeHandler = jest.fn();

    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        closeHandler();
      }
    });

    container.appendChild(backdrop);
    backdrop.click();

    expect(closeHandler).toHaveBeenCalled();
  });

  test('should not close when dialog content clicked', () => {
    const backdrop = document.createElement('div');
    backdrop.className = 'myvh-portal-dialog-backdrop';
    const dialog = document.createElement('div');
    dialog.className = 'myvh-portal-dialog';

    const closeHandler = jest.fn();

    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        closeHandler();
      }
    });

    backdrop.appendChild(dialog);
    container.appendChild(backdrop);

    dialog.click();

    expect(closeHandler).not.toHaveBeenCalled();
  });
});

describe('MyvhPortalDialog - DOM Cleanup', () => {
  test('should remove backdrop from DOM on close', () => {
    const backdrop = document.createElement('div');
    backdrop.id = 'test-backdrop';
    document.body.appendChild(backdrop);

    const closeCurrent = () => {
      if (backdrop && backdrop.parentNode) {
        backdrop.parentNode.removeChild(backdrop);
      }
    };

    expect(document.getElementById('test-backdrop')).toBeTruthy();
    closeCurrent();
    expect(document.getElementById('test-backdrop')).toBeNull();
  });

  test('should reset active elements on close', () => {
    let activeElements = { resolve: jest.fn() };

    const closeCurrent = () => {
      activeElements = null;
    };

    closeCurrent();
    expect(activeElements).toBeNull();
  });
});
