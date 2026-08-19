import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CalendarDays, FileText, Scale } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '../marketplace/utils';

type LegalDocument = {
    type: string;
    slug: string;
    version: string;
    title: string;
    summary: string | null;
    body: string;
    published_at: string | null;
    is_draft_placeholder: boolean;
};

type LegalDocumentLink = {
    title: string;
    slug: string;
    version: string;
    url: string;
};

type Props = {
    document: LegalDocument;
    documents: Record<string, LegalDocumentLink>;
};

export default function LegalShow({ document, documents }: Props) {
    const documentLinks = Object.values(documents);

    return (
        <>
            <Head title={document.title} />

            <div className="bg-background">
                <section className="border-b bg-muted/20">
                    <div className="mx-auto grid w-full max-w-7xl gap-6 px-4 py-8 md:px-6 md:py-10 lg:grid-cols-[minmax(0,1fr)_21rem]">
                        <div className="flex min-w-0 flex-col justify-center">
                            <Badge
                                variant="secondary"
                                className="mb-4 w-fit rounded-md"
                            >
                                <Scale className="size-3.5" />
                                Dokumenty platformy
                            </Badge>
                            <h1 className="text-3xl font-semibold tracking-normal md:text-4xl">
                                {document.title}
                            </h1>
                            {document.summary && (
                                <p className="mt-4 max-w-3xl text-base leading-7 text-muted-foreground">
                                    {document.summary}
                                </p>
                            )}
                            <div className="mt-5 flex flex-wrap gap-2 text-sm text-muted-foreground">
                                <Badge variant="outline">
                                    wersja {document.version}
                                </Badge>
                                <Badge variant="outline">
                                    <CalendarDays className="size-3" />
                                    {formatDate(document.published_at)}
                                </Badge>
                            </div>
                        </div>

                        <Card className="h-fit shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileText className="size-4 text-muted-foreground" />
                                    Pozostałe dokumenty
                                </CardTitle>
                                <CardDescription>
                                    Publiczny zestaw dokumentów MVP
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-2">
                                {documentLinks.map((item) => (
                                    <Button
                                        key={item.slug}
                                        asChild
                                        variant={
                                            item.slug === document.slug
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        className="justify-start"
                                    >
                                        <Link href={item.url}>
                                            {item.title}
                                        </Link>
                                    </Button>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-7xl px-4 py-8 md:px-6 md:py-10">
                    {document.is_draft_placeholder && (
                        <Alert className="mb-6 border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-500/40 dark:bg-amber-950/30 dark:text-amber-100">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Treść robocza MVP</AlertTitle>
                            <AlertDescription>
                                Ten dokument jest technicznym placeholderem i
                                wymaga finalnej weryfikacji prawnej przed
                                produkcyjnym uruchomieniem.
                            </AlertDescription>
                        </Alert>
                    )}

                    <article className="rounded-lg border bg-card p-5 shadow-sm md:p-8">
                        <div className="prose prose-neutral dark:prose-invert max-w-none">
                            <pre className="font-sans text-sm leading-7 whitespace-pre-wrap text-foreground">
                                {document.body}
                            </pre>
                        </div>
                    </article>
                </section>
            </div>
        </>
    );
}
