import AppLogoIcon from '@/components/app-logo-icon';
import { FlashMessages } from '@/components/flash-messages';
import type { PublicBusiness } from '@/types';
import { MapPin, Phone } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';

/** Branded shell for a business' public pages: uses the business accent colour. */
export default function PublicLayout({ business, children }: { business: PublicBusiness; children: ReactNode }) {
    const style = { '--brand': business.accent_color } as CSSProperties;

    return (
        <div style={style} className="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
            <header className="relative overflow-hidden bg-[var(--brand)] text-white">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_-20%,rgba(255,255,255,0.35),transparent_45%),radial-gradient(circle_at_90%_120%,rgba(0,0,0,0.25),transparent_40%)]" />
                <div className="relative mx-auto flex max-w-5xl items-center gap-4 px-4 py-8 md:py-10">
                    <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl font-bold ring-1 ring-white/30 backdrop-blur">
                        {business.name.charAt(0)}
                    </span>
                    <div className="min-w-0">
                        <p className="text-sm text-white/75">{business.category}</p>
                        <h1 className="truncate text-2xl font-bold tracking-tight md:text-3xl">{business.name}</h1>
                        {business.tagline && <p className="mt-0.5 text-white/85">{business.tagline}</p>}
                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-white/80">
                            {business.address && (
                                <span className="flex items-center gap-1">
                                    <MapPin className="size-3.5" /> {business.address}
                                </span>
                            )}
                            {business.phone && (
                                <span className="flex items-center gap-1">
                                    <Phone className="size-3.5" /> {business.phone}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-5xl px-4 py-8">{children}</main>
            <footer className="text-muted-foreground mx-auto flex max-w-5xl items-center justify-center gap-1.5 px-4 pb-10 text-xs">
                <AppLogoIcon className="size-3.5" /> Reservas online con <span className="font-semibold">Turnero</span>
            </footer>
            <FlashMessages />
        </div>
    );
}
