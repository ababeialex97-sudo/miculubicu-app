// Shapes returned by the mlb-app-api WordPress plugin (/wp-json/mlb/v1).

export type Image = {
  url: string;
  thumbnail: string;
  alt: string;
};

export type Variation = {
  id: number;
  name: string;
  attributes: Record<string, string>;
  price: string;
  regular_price: string;
  in_stock: boolean;
};

export type Product = {
  id: number;
  name: string;
  slug: string;
  type: 'simple' | 'variable';
  short_description: string;
  description: string;
  price: string;
  regular_price: string;
  on_sale: boolean;
  in_stock: boolean;
  weight: { value: string; unit: string } | null;
  image: Image | null;
  gallery: Image[];
  category_ids: number[];
  variations: Variation[];
  preference_options: string[];
};

export type Category = {
  id: number;
  name: string;
  slug: string;
  description: string;
  image: Image | null;
  product_ids: number[];
};

export type Menu = {
  currency: string;
  categories: Category[];
  products: Product[];
};

export type Location = {
  id: string;
  name: string;
  address: string;
  phone: string;
  delivery: boolean;
  pickup: boolean;
  delivery_fee: string;
  free_delivery_threshold: string;
};

export type OrderStatus =
  | 'received'
  | 'confirmed'
  | 'preparing'
  | 'on_the_way'
  | 'ready_for_pickup'
  | 'completed'
  | 'cancelled';

export type Config = {
  currency: string;
  locations: Location[];
  payment_methods: { id: string; title: string }[];
  statuses: Record<OrderStatus, string>;
  test_mode: boolean;
};

export type Address = {
  address_1: string;
  address_2: string;
  city: string;
};

export type Customer = {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  phone: string;
  address: Address;
};

export type Session = {
  token: string;
  customer: Customer;
};

export type Fulfillment = 'delivery' | 'pickup';

export type OrderItem = {
  product_id: number;
  variation_id: number;
  name: string;
  quantity: number;
  total: string;
  preferences: string;
  image: Image | null;
};

export type Order = {
  id: number;
  number: string;
  created_at: string | null;
  status: OrderStatus;
  status_label: string;
  fulfillment: Fulfillment | '';
  location_id: string;
  items: OrderItem[];
  subtotal: string;
  shipping_total: string;
  discount_total: string;
  total: string;
  currency: string;
  payment_method: string;
  address: Address;
  phone: string;
  note: string;
};

export type NewOrder = {
  client_order_id: string;
  location_id: string;
  fulfillment: Fulfillment;
  address?: Partial<Address>;
  phone: string;
  note: string;
  payment_method: string;
  items: {
    product_id: number;
    variation_id?: number;
    quantity: number;
    preferences?: string;
  }[];
};
