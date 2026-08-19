export type PublicMedia = {
    id: number;
    url: string | null;
    storage_path: string;
    type?: 'IMAGE' | 'DOCUMENT';
    sort_order: number;
    is_primary: boolean;
};

export type PublicVenue = {
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    city: string | null;
    voivodeship: string | null;
    latitude: string | null;
    longitude: string | null;
    venue_type: 'PRIVATE' | 'PZW' | 'WODY_POLSKIE' | 'OTHER_PUBLIC' | 'OTHER';
    commercial_status: 'INFORMATION_ONLY' | 'CLAIMABLE' | 'BOOKABLE';
    is_bookable: boolean;
    spots_count: number;
    media_count: number;
    primary_media: PublicMedia | null;
    distance_km: number | null;
    available_spots_count: number | null;
    starting_price_amount: number | null;
    currency: string;
    rating_average: number | null;
    reviews_count: number;
    is_favorited: boolean;
    fish_species?: {
        name: string;
        slug: string;
    }[];
    amenities?: {
        name: string;
        slug: string;
        icon: string | null;
    }[];
};
