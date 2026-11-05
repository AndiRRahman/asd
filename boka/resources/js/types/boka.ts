export type ImageData = {
    url: string;
    width: number;
    height: number;
    color: string | null;
};

export type ProducerRef = {
    name: string;
    slug: string;
    type: 'karang_taruna' | 'umkm';
    district: string;
    village?: string;
    story?: string | null;
};

export type ProductCard = {
    id: string;
    name: string;
    slug: string;
    image: ImageData | null;
    price: number;
    compare_price: number | null;
    is_flash: boolean;
    producer: ProducerRef | null;
    rating: number | null;
    rating_count: number;
    sold: number;
    in_stock: boolean;
};

export type Variant = {
    id: string;
    label: string;
    options: Record<string, string> | null;
    price: number;
    compare_price: number | null;
    flash_ends_at: string | null;
    stock_exact: number | null;
    max_buyable: number;
    weight_gram: number;
};

export type ProductDetail = ProductCard & {
    description: string | null;
    brewing_instructions?: string | null;
    images: ImageData[];
    category: {
        name: string;
        slug: string;
        parent: { name: string; slug: string } | null;
    } | null;
    tags: string[];
    origin: { name: string; district: string; pickup: boolean } | null;
    variants: Variant[];
};

export type Review = {
    id: string;
    rating: number;
    body: string | null;
    reply: string | null;
    author: string;
    product: { name: string; slug: string } | null;
    date: string;
};

export type CategoryRef = { name: string; slug: string; icon?: string | null };

export type CartLine = {
    variant_id: string;
    product: {
        name: string;
        slug: string;
        image: ImageData | null;
        producer: string | null;
        origin: string | null;
    };
    variant_label: string | null;
    qty: number;
    unit_price: number;
    compare_price: number | null;
    line_total: number;
    price_changed: boolean;
    issue: 'habis' | 'sisa' | null;
    available: number;
};

export type ShippingOption = {
    key: string;
    method: 'ekspedisi' | 'lokal' | 'ambil';
    courier: string | null;
    service: string | null;
    label: string;
    cost: number;
    etd: string | null;
};

export type Quote = {
    packages: {
        number: number;
        origin: { id: string; name: string; district: string; address: string; pickup_hours: string | null };
        weight_gram: number;
        items: CartLine[];
        options: ShippingOption[];
        selected: string | null;
    }[];
    subtotal: number;
    voucher_discount: number;
    shipping_cost: number;
    shipping_discount: number;
    service_fee: number;
    grand_total: number;
    voucher_errors: Partial<Record<'belanja' | 'ongkir', string>>;
    expedition_unavailable: boolean;
    has_flash_sale: boolean;
};

export type Address = {
    id: string;
    label: string;
    recipient: string;
    phone: string;
    destination_id: string;
    district: string;
    destination_label: string;
    full_address: string;
    postal_code: string | null;
    is_default: boolean;
};

export type OrderStatus =
    | 'menunggu_pembayaran'
    | 'menunggu_verifikasi'
    | 'diproses'
    | 'dikirim'
    | 'selesai'
    | 'kedaluwarsa'
    | 'dibatalkan';

export type OrderSummary = {
    code: string;
    status: OrderStatus;
    grand_total: number;
    item_count: number;
    first_item: string | null;
    payment_deadline_at: string | null;
    created_at: string;
};

export type Shipment = {
    id: string;
    number: number;
    origin: { name: string; address: string; district: string; pickup_hours: string | null; contact: string | null };
    method: 'ekspedisi' | 'lokal' | 'ambil';
    courier: string | null;
    service: string | null;
    cost: number;
    weight_gram: number;
    tracking_number: string | null;
    status: string;
    receiver_name: string | null;
    shipped_at: string | null;
    pickup_code: string | null;
    item_ids: string[];
};

export type OrderDetail = OrderSummary & {
    subtotal: number;
    voucher_discount: number;
    shipping_cost: number;
    shipping_discount: number;
    service_fee: number;
    unique_code: number;
    rejection_count: number;
    bank: { bank_name: string; account_number: string; account_holder: string };
    address: Omit<Address, 'id' | 'is_default'>;
    cancel_reason: string | null;
    proof: {
        status: string;
        reject_reason: string | null;
        sender_bank: string;
        sender_name: string;
        uploaded_at: string;
    } | null;
    shipments: Shipment[];
    items: {
        id: string;
        product_id: string;
        product_name: string;
        variant_name: string | null;
        unit_price: number;
        qty: number;
        shipment_id: string;
        reviewed: boolean;
    }[];
    histories: { to: OrderStatus; note: string | null; at: string }[];
    can_upload_proof: boolean;
    can_cancel: boolean;
    can_complete: boolean;
    can_review: boolean;
};

export type PostCard = {
    title: string;
    slug: string;
    type: 'berita' | 'dokumentasi';
    excerpt: string | null;
    cover: string | null;
    location: string | null;
    published_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total?: number;
};
