export type Member = {
  id: number;
  member_number: string | null;
  full_name: string;
  first_name: string | null;
  last_name: string | null;
  organization: string | null;
  email: string | null;
  mobile: string | null;
  landline: string | null;
  street: string | null;
  address_addition: string | null;
  zip: string | null;
  city: string | null;
  country: string | null;
  entry_date: string | null;
};

export type Tenant = {
  id: number;
  name: string;
  slug: string;
};

export type EventInvitation = {
  status: string;
  label: string;
  note: string | null;
  responded_at: string | null;
};

export type ClubEvent = {
  id: number;
  title: string;
  description: string | null;
  location: string | null;
  starts_at: string;
  ends_at: string;
  category: string | null;
  response_required: boolean;
  booking_enabled: boolean;
  price_label: string;
  mobile_shifts_enabled: boolean;
  invitation: EventInvitation | null;
};

export type Shift = {
  id: number;
  title: string;
  role: string | null;
  starts_at: string;
  ends_at: string;
  required_people: number;
  open_slots: number;
  coverage_status: string;
  notes: string | null;
  assignments: Array<{ id: number; name: string; is_me: boolean }>;
};

export type DocumentItem = {
  id: number;
  title: string;
  description: string | null;
  document_date: string | null;
  mime_type: string | null;
  size: number;
};
