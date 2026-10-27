// Orders placed without an account: what the device keeps so it can follow them.

/** The server's limit on how many guest orders one request may name. */
export const MAX_GUEST_ORDERS = 20;

export type GuestOrderRef = {
  id: number;
  token: string;
};

export type GuestContact = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
};

/** Newest first, one entry per order, at most MAX_GUEST_ORDERS. */
export function addGuestOrder(refs: GuestOrderRef[], ref: GuestOrderRef): GuestOrderRef[] {
  return [ref, ...refs.filter((r) => r.id !== ref.id)].slice(0, MAX_GUEST_ORDERS);
}

export function guestTokenFor(refs: GuestOrderRef[], orderId: number): string | null {
  return refs.find((r) => r.id === orderId)?.token ?? null;
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Field errors for the guest checkout form, in the customer's words. */
export function guestContactErrors(contact: GuestContact): Partial<Record<keyof GuestContact, string>> {
  const errors: Partial<Record<keyof GuestContact, string>> = {};
  if (!contact.first_name.trim()) {
    errors.first_name = 'Scrie-ne numele tău.';
  }
  if (contact.email.trim() && !EMAIL_PATTERN.test(contact.email.trim())) {
    errors.email = 'Adresa de email nu pare corectă.';
  }
  return errors;
}
