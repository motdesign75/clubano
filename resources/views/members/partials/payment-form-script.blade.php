@push('scripts')
<script>
    if (!window.memberPaymentForm) {
        window.memberPaymentForm = function (config) {
            return {
                paymentMethod: config.paymentMethod || '',
                iban: config.iban || '',
                bic: config.bic || '',
                bicHint: '',
                bicAutoResolved: false,
                lookupUrl: config.lookupUrl,

                async lookupBic() {
                    if (this.paymentMethod !== 'sepa_lastschrift') {
                        return;
                    }

                    const rawIban = (this.iban || '').replace(/\s+/g, '').toUpperCase();
                    this.iban = rawIban;

                    if (!rawIban || !rawIban.startsWith('DE') || rawIban.length < 12) {
                        this.bicHint = '';
                        return;
                    }

                    try {
                        const response = await fetch(`${this.lookupUrl}?iban=${encodeURIComponent(rawIban)}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        this.iban = payload.iban || rawIban;

                        if (payload.found && payload.bic && (!this.bic || this.bicAutoResolved)) {
                            this.bic = payload.bic;
                            this.bicAutoResolved = true;
                            this.bicHint = `BIC automatisch ergänzt (${payload.source_label}).`;
                            return;
                        }

                        if (!payload.found && !this.bic) {
                            this.bicHint = 'Keine BIC automatisch gefunden. Bitte kurz ergänzen.';
                        }
                    } catch (error) {
                        this.bicHint = '';
                    }
                },
            };
        }
    }
</script>
@endpush
