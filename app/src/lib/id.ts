/** Random ID for idempotent order submission. Not a security token. */
export function randomId(): string {
  const random = () => Math.random().toString(36).slice(2, 10);
  return `${Date.now().toString(36)}-${random()}-${random()}`;
}
