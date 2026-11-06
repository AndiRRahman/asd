import React, { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
    children: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null,
    };

    public static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error('Uncaught error in UI component:', error, errorInfo);
    }

    public render() {
        if (this.state.hasError) {
            return (
                <div className="mx-auto my-12 max-w-xl rounded-2xl border border-destructive/30 bg-destructive/5 p-6 text-center">
                    <h2 className="text-lg font-bold text-destructive">Terjadi Kendala Tampilan</h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {this.state.error?.message || 'Gagal memuat komponen halaman ini.'}
                    </p>
                    <div className="mt-4 flex justify-center gap-3">
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
                        >
                            Muat Ulang Halaman
                        </button>
                        <a
                            href="/"
                            className="rounded-lg border border-input bg-card px-4 py-2 text-sm font-semibold hover:bg-muted"
                        >
                            Kembali ke Beranda
                        </a>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
