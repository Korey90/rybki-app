import { ExternalLink, MapPinned } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

type VenueMapPoint = {
    uuid: string;
    name: string;
    slug: string;
    latitude: string | null;
    longitude: string | null;
    city?: string | null;
    voivodeship?: string | null;
    distance_km?: number | null;
};

type GoogleMapsNamespace = {
    maps: {
        Map: new (
            element: HTMLElement,
            options: Record<string, unknown>,
        ) => GoogleMapInstance;
        Marker: new (options: Record<string, unknown>) => GoogleMarkerInstance;
        LatLngBounds: new () => GoogleLatLngBoundsInstance;
        event: {
            addListener: (
                instance: GoogleMarkerInstance,
                eventName: string,
                handler: () => void,
            ) => void;
        };
    };
};

type GoogleMapInstance = {
    fitBounds: (bounds: GoogleLatLngBoundsInstance) => void;
    setCenter: (position: { lat: number; lng: number }) => void;
    setZoom: (zoom: number) => void;
};

type GoogleMarkerInstance = {
    setMap: (map: GoogleMapInstance | null) => void;
};

type GoogleLatLngBoundsInstance = {
    extend: (position: { lat: number; lng: number }) => void;
};

declare global {
    interface Window {
        google?: GoogleMapsNamespace;
        __rybkiGoogleMapsPromise?: Promise<GoogleMapsNamespace>;
        __rybkiGoogleMapsLoaded?: () => void;
    }
}

export function GoogleVenueMap({
    venues,
    className = '',
}: {
    venues: VenueMapPoint[];
    className?: string;
}) {
    const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as
        string | undefined;
    const mapRef = useRef<HTMLDivElement | null>(null);
    const markersRef = useRef<GoogleMarkerInstance[]>([]);
    const [status, setStatus] = useState<'idle' | 'ready' | 'missing-key'>(
        apiKey ? 'idle' : 'missing-key',
    );
    const positionedVenues = useMemo(
        () =>
            venues
                .map((venue) => ({
                    ...venue,
                    lat: Number(venue.latitude),
                    lng: Number(venue.longitude),
                }))
                .filter(
                    (venue) =>
                        Number.isFinite(venue.lat) &&
                        Number.isFinite(venue.lng),
                ),
        [venues],
    );

    useEffect(() => {
        if (!apiKey || !mapRef.current || positionedVenues.length === 0) {
            return;
        }

        let cancelled = false;

        loadGoogleMaps(apiKey)
            .then((google) => {
                if (cancelled || !mapRef.current) {
                    return;
                }

                markersRef.current.forEach((marker) => marker.setMap(null));
                markersRef.current = [];

                const firstVenue = positionedVenues[0];
                const map = new google.maps.Map(mapRef.current, {
                    center: { lat: firstVenue.lat, lng: firstVenue.lng },
                    zoom: positionedVenues.length === 1 ? 12 : 7,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                });
                const bounds = new google.maps.LatLngBounds();

                positionedVenues.forEach((venue) => {
                    const position = { lat: venue.lat, lng: venue.lng };
                    const marker = new google.maps.Marker({
                        map,
                        position,
                        title: venue.name,
                    });

                    google.maps.event.addListener(marker, 'click', () => {
                        window.location.href = `/lowiska/${venue.slug}`;
                    });

                    markersRef.current.push(marker);
                    bounds.extend(position);
                });

                if (positionedVenues.length > 1) {
                    map.fitBounds(bounds);
                } else {
                    map.setCenter({
                        lat: firstVenue.lat,
                        lng: firstVenue.lng,
                    });
                    map.setZoom(12);
                }

                setStatus('ready');
            })
            .catch(() => setStatus('missing-key'));

        return () => {
            cancelled = true;
            markersRef.current.forEach((marker) => marker.setMap(null));
            markersRef.current = [];
        };
    }, [apiKey, positionedVenues]);

    if (positionedVenues.length === 0) {
        return (
            <MapFallback
                venues={[]}
                message="Brak współrzędnych dla wybranych łowisk."
                className={className}
            />
        );
    }

    if (status === 'missing-key') {
        return (
            <MapFallback
                venues={positionedVenues}
                message="Mapa Google wymaga klucza VITE_GOOGLE_MAPS_API_KEY."
                className={className}
            />
        );
    }

    return (
        <div
            ref={mapRef}
            className={`min-h-80 overflow-hidden rounded-lg border bg-muted ${className}`}
        />
    );
}

function MapFallback({
    venues,
    message,
    className,
}: {
    venues: (VenueMapPoint & { lat?: number; lng?: number })[];
    message: string;
    className: string;
}) {
    return (
        <div
            className={`rounded-lg border bg-muted/40 p-4 ${className}`}
            data-map-status="fallback"
        >
            <div className="flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-background">
                    <MapPinned className="size-5 text-primary" />
                </span>
                <div>
                    <div className="font-medium">Mapa lokalizacji</div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {message}
                    </p>
                </div>
            </div>

            {venues.length > 0 && (
                <div className="mt-4 space-y-2">
                    {venues.slice(0, 8).map((venue) => (
                        <div
                            key={venue.uuid}
                            className="flex items-center justify-between gap-3 rounded-md border bg-background p-3 text-sm"
                        >
                            <div className="min-w-0">
                                <div className="truncate font-medium">
                                    {venue.name}
                                </div>
                                <div className="truncate text-muted-foreground">
                                    {venue.city ?? 'Bez miasta'}
                                    {venue.voivodeship
                                        ? `, ${venue.voivodeship}`
                                        : ''}
                                    {venue.distance_km !== null &&
                                    venue.distance_km !== undefined
                                        ? ` · ${venue.distance_km.toFixed(1)} km`
                                        : ''}
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={`https://www.google.com/maps/search/?api=1&query=${venue.latitude},${venue.longitude}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                </a>
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function loadGoogleMaps(apiKey: string): Promise<GoogleMapsNamespace> {
    if (window.google?.maps) {
        return Promise.resolve(window.google);
    }

    if (window.__rybkiGoogleMapsPromise) {
        return window.__rybkiGoogleMapsPromise;
    }

    window.__rybkiGoogleMapsPromise = new Promise((resolve, reject) => {
        window.__rybkiGoogleMapsLoaded = () => {
            if (window.google?.maps) {
                resolve(window.google);
            } else {
                reject(new Error('Google Maps API did not initialize.'));
            }
        };

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&v=weekly&callback=__rybkiGoogleMapsLoaded`;
        script.async = true;
        script.defer = true;
        script.onerror = () =>
            reject(new Error('Google Maps API could not be loaded.'));
        document.head.appendChild(script);
    });

    return window.__rybkiGoogleMapsPromise;
}
