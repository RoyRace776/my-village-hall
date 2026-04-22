/**
 * Sample Jest Test
 * This is a simple example to demonstrate Jest setup and testing patterns
 */

describe('Sample Math Tests', () => {
  test('should add two numbers correctly', () => {
    const result = 2 + 2;
    expect(result).toBe(4);
  });

  test('should subtract two numbers correctly', () => {
    const result = 10 - 3;
    expect(result).toBe(7);
  });

  test('should handle arrays', () => {
    const arr = [1, 2, 3];
    expect(arr).toHaveLength(3);
    expect(arr).toContain(2);
  });

  test('should handle objects', () => {
    const user = { name: 'John', age: 30 };
    expect(user).toHaveProperty('name');
    expect(user.age).toBe(30);
  });
});

describe('String Utilities', () => {
  test('should capitalize a string', () => {
    const capitalize = (str) => str.charAt(0).toUpperCase() + str.slice(1);
    expect(capitalize('hello')).toBe('Hello');
  });

  test('should trim whitespace', () => {
    const str = '  hello world  ';
    expect(str.trim()).toBe('hello world');
  });
});
