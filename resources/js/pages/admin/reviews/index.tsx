import { Form, Head, Link } from '@inertiajs/react';
import { Check, EyeOff, ShieldAlert, Star } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/pages/marketplace/utils';

type ReviewStatus = 'PUBLISHED' | 'HIDDEN';

type AdminReview = {
    uuid: string;
    rating: number;
    title: string | null;
    body: string | null;
    status: ReviewStatus;
    hidden_reason: string | null;
    operator_response: string | null;
    operator_responded_at: string | null;
    created_at: string | null;
    moderated_at: string | null;
    venue: {
        name: string;
        slug: string;
        organization: string | null;
    };
    booking: {
        booking_number: string;
        uuid: string;
    };
    author: {
        name: string;
        email: string;
    };
    moderator: {
        name: string;
        email: string;
    } | null;
    operator_responder: {
        name: string;
        email: string;
    } | null;
};

const statusLabels: Record<ReviewStatus, string> = {
    PUBLISHED: 'Opublikowane',
    HIDDEN: 'Ukryte',
};

export default function AdminReviewsIndex({
    reviews,
}: {
    reviews: AdminReview[];
}) {
    return (
        <>
            <Head title="Moderacja opinii" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Moderacja opinii"
                    description="Ukrywanie opinii naruszających zasady platformy"
                />

                <section className="space-y-4">
                    {reviews.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            Brak opinii.
                        </div>
                    ) : (
                        reviews.map((review) => (
                            <ReviewCard key={review.uuid} review={review} />
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

function ReviewCard({ review }: { review: AdminReview }) {
    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <ShieldAlert className="size-4 text-muted-foreground" />
                        {review.venue.name}
                    </CardTitle>
                    <CardDescription>
                        {review.author.name} · {review.booking.booking_number}
                    </CardDescription>
                </div>
                <div className="flex flex-wrap gap-2 sm:justify-end">
                    <StarRating rating={review.rating} />
                    <Badge
                        variant={
                            review.status === 'HIDDEN'
                                ? 'destructive'
                                : 'secondary'
                        }
                    >
                        {statusLabels[review.status]}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="grid gap-3 text-sm md:grid-cols-4">
                    <Info
                        label="Dodano"
                        value={formatDateTime(review.created_at)}
                    />
                    <Info label="Autor" value={review.author.email} />
                    <Info
                        label="Organizacja"
                        value={review.venue.organization ?? '-'}
                    />
                    <Info
                        label="Moderacja"
                        value={formatDateTime(review.moderated_at)}
                    />
                </div>

                <div className="rounded-md border bg-muted/30 p-3 text-sm">
                    <div className="font-medium">
                        {review.title ?? 'Opinia wędkarza'}
                    </div>
                    {review.body && (
                        <p className="mt-2 leading-6 text-muted-foreground">
                            {review.body}
                        </p>
                    )}
                    {review.operator_response && (
                        <div className="mt-3 border-l-2 pl-3">
                            <div className="text-xs font-medium">
                                Odpowiedź operatora
                            </div>
                            <p className="mt-1 leading-6 text-muted-foreground">
                                {review.operator_response}
                            </p>
                        </div>
                    )}
                    {review.hidden_reason && (
                        <p className="mt-2 text-xs text-destructive">
                            {review.hidden_reason}
                        </p>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <ModerationForm review={review} status="HIDDEN" />
                    <div className="flex flex-col gap-2">
                        <ModerationForm review={review} status="PUBLISHED" />
                        <Button asChild variant="outline">
                            <Link href={`/lowiska/${review.venue.slug}`}>
                                Otwórz profil
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function ModerationForm({
    review,
    status,
}: {
    review: AdminReview;
    status: ReviewStatus;
}) {
    const isHidden = status === 'HIDDEN';

    return (
        <Form
            action={`/admin/reviews/${review.uuid}`}
            method="patch"
            className={isHidden ? 'space-y-3' : ''}
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="status" value={status} />
                    {isHidden && (
                        <div className="grid gap-2">
                            <Label htmlFor={`hidden_reason_${review.uuid}`}>
                                Powód ukrycia
                            </Label>
                            <Textarea
                                id={`hidden_reason_${review.uuid}`}
                                name="hidden_reason"
                                defaultValue={review.hidden_reason ?? ''}
                                rows={3}
                                required
                            />
                            <InputError message={errors.hidden_reason} />
                        </div>
                    )}

                    <Button
                        type="submit"
                        disabled={processing}
                        variant={isHidden ? 'destructive' : 'secondary'}
                        className="w-full"
                    >
                        {isHidden ? (
                            <EyeOff className="size-4" />
                        ) : (
                            <Check className="size-4" />
                        )}
                        {isHidden ? 'Ukryj opinię' : 'Opublikuj'}
                    </Button>
                </>
            )}
        </Form>
    );
}

function StarRating({ rating }: { rating: number }) {
    return (
        <div className="flex items-center gap-0.5" aria-label={`${rating}/5`}>
            {Array.from({ length: 5 }).map((_, index) => (
                <Star
                    key={index}
                    className={
                        index < rating
                            ? 'size-4 fill-current text-primary'
                            : 'size-4 text-muted-foreground'
                    }
                />
            ))}
        </div>
    );
}

function Info({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <span className="block text-xs text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}

AdminReviewsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Moderacja opinii',
            href: '/admin/reviews',
        },
    ],
};
