import { Form, Head, Link } from '@inertiajs/react';
import { MessageSquareReply, Send, Star } from 'lucide-react';
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

type OperatorReview = {
    uuid: string;
    rating: number;
    title: string | null;
    body: string | null;
    status: ReviewStatus;
    hidden_reason: string | null;
    operator_response: string | null;
    operator_responded_at: string | null;
    created_at: string | null;
    venue: {
        name: string;
        slug: string;
    };
    booking: {
        booking_number: string;
        uuid: string;
    };
    author: {
        name: string;
        email: string;
    };
};

const statusLabels: Record<ReviewStatus, string> = {
    PUBLISHED: 'Opublikowane',
    HIDDEN: 'Ukryte',
};

const statuses = Object.keys(statusLabels) as ReviewStatus[];

export default function OperatorReviewsIndex({
    reviews,
    filters,
    status_counts,
}: {
    reviews: OperatorReview[];
    filters: {
        status: ReviewStatus | null;
    };
    status_counts: Record<ReviewStatus, number>;
}) {
    const totalCount = Object.values(status_counts).reduce(
        (sum, value) => sum + value,
        0,
    );

    return (
        <>
            <Head title="Opinie łowisk" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Opinie"
                    description="Oceny wędkarzy i odpowiedzi operatora"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Filtry</CardTitle>
                        <CardDescription>
                            Widoczność opinii na profilach publicznych
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <Button
                            asChild
                            variant={
                                filters.status === null ? 'default' : 'outline'
                            }
                            size="sm"
                        >
                            <Link href="/operator/reviews">
                                Wszystkie
                                <Badge variant="secondary">{totalCount}</Badge>
                            </Link>
                        </Button>
                        {statuses.map((status) => (
                            <Button
                                key={status}
                                asChild
                                variant={
                                    filters.status === status
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                            >
                                <Link
                                    href={`/operator/reviews?status=${status}`}
                                >
                                    {statusLabels[status]}
                                    <Badge variant="secondary">
                                        {status_counts[status] ?? 0}
                                    </Badge>
                                </Link>
                            </Button>
                        ))}
                    </CardContent>
                </Card>

                <section className="space-y-4">
                    {reviews.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            Brak opinii dla wybranego widoku.
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

function ReviewCard({ review }: { review: OperatorReview }) {
    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <MessageSquareReply className="size-4 text-muted-foreground" />
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
                <div className="grid gap-3 text-sm md:grid-cols-3">
                    <Info
                        label="Dodano"
                        value={formatDateTime(review.created_at)}
                    />
                    <Info label="Autor" value={review.author.email} />
                    <Info
                        label="Odpowiedź"
                        value={formatDateTime(review.operator_responded_at)}
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
                    {review.hidden_reason && (
                        <p className="mt-2 text-xs text-destructive">
                            {review.hidden_reason}
                        </p>
                    )}
                </div>

                <Form
                    action={`/operator/reviews/${review.uuid}/response`}
                    method="patch"
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`response_${review.uuid}`}>
                                    Odpowiedź operatora
                                </Label>
                                <Textarea
                                    id={`response_${review.uuid}`}
                                    name="operator_response"
                                    defaultValue={
                                        review.operator_response ?? ''
                                    }
                                    rows={4}
                                    required
                                />
                                <InputError
                                    message={errors.operator_response}
                                />
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={processing}>
                                    <Send className="size-4" />
                                    Zapisz odpowiedź
                                </Button>
                                <Button asChild variant="outline">
                                    <Link
                                        href={`/lowiska/${review.venue.slug}`}
                                    >
                                        Otwórz profil
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
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

OperatorReviewsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Opinie',
            href: '/operator/reviews',
        },
    ],
};
