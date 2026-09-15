import { Button } from '@/components/ui/button';
import type { Round } from '@/types/procurement';

function escape(value: string | number | null | undefined): string {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function FrozenEvidence({ round }: { round: Round }) {
    const snapshot = round.frozen_snapshot;
    if (!snapshot) return null;
    const recommended = snapshot.candidates.find(
        (candidate) => candidate.uuid === round.recommended_candidate_uuid,
    );
    function downloadReport() {
        if (!snapshot) return;
        const header =
            '<!doctype html><html lang="en"><meta charset="utf-8"><title>Procurement evidence</title><style>body{font:14px system-ui;max-width:1100px;margin:40px auto;padding:0 20px;color:#17201d}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #ccd5d0;text-align:left;padding:10px;vertical-align:top}h2{margin-top:32px}p{white-space:pre-wrap}@media print{body{margin:0}section{break-inside:avoid}}</style><body>';
        const report =
            header +
            '<h1>' +
            escape(snapshot.request.request_number) +
            ': ' +
            escape(snapshot.request.title) +
            '</h1><p>Evaluation round ' +
            round.round_number +
            ' · Frozen ' +
            escape(snapshot.generated_at) +
            '</p><p>Report generated ' +
            escape(new Date().toISOString()) +
            '</p><h2>Recommendation</h2><p>' +
            escape(recommended?.quotation_revision.supplier_name_snapshot) +
            '</p><p>' +
            escape(round.recommendation_reason) +
            '</p><p>Participation: ' +
            escape(round.participation_justification) +
            '</p><p>Decision: ' +
            escape(round.approval?.status ?? 'Pending') +
            ' · ' +
            escape(round.approval?.decided_at) +
            '</p><p>' +
            escape(round.approval?.decision_reason) +
            '</p>' +
            snapshot.candidates
                .map((candidate) => {
                    const quote = candidate.quotation_revision;
                    return (
                        '<section><h2>' +
                        escape(quote.supplier_name_snapshot) +
                        ' · Revision ' +
                        quote.revision_number +
                        '</h2><p>Revision identifier: ' +
                        escape(quote.uuid) +
                        '</p><p>Disposition: ' +
                        escape(candidate.disposition) +
                        ' · ' +
                        escape(candidate.disposition_reason) +
                        '</p><p>Total: ' +
                        escape(quote.currency_code) +
                        ' ' +
                        escape(quote.total_amount) +
                        ' · Delivery charge: ' +
                        escape(quote.delivery_charge) +
                        '</p><p>Delivery: ' +
                        quote.delivery_days +
                        ' days · Valid through: ' +
                        escape(quote.valid_until?.slice(0, 10)) +
                        '</p><p>Payment: ' +
                        escape(quote.payment_terms) +
                        '</p><table><tr><th>Item</th><th>Quantity</th><th>Unit price</th><th>Discount</th><th>Net</th><th>Tax</th><th>Total</th></tr>' +
                        quote.lines
                            .map(
                                (line) =>
                                    '<tr><td>' +
                                    escape(line.request_item.description) +
                                    '</td><td>' +
                                    escape(line.request_item.quantity) +
                                    ' ' +
                                    escape(line.request_item.unit) +
                                    '</td><td>' +
                                    escape(line.unit_price) +
                                    '</td><td>' +
                                    escape(line.discount_amount) +
                                    '</td><td>' +
                                    escape(line.line_net) +
                                    '</td><td>' +
                                    escape(line.tax_amount) +
                                    '</td><td>' +
                                    escape(line.line_total) +
                                    '</td></tr>',
                            )
                            .join('') +
                        '</table><h3>Specification findings</h3>' +
                        candidate.line_assessments
                            .map(
                                (finding) =>
                                    '<p>' +
                                    escape(finding.result) +
                                    ': ' +
                                    escape(finding.evidence_note) +
                                    '</p>',
                            )
                            .join('') +
                        '<h3>Criterion findings</h3>' +
                        candidate.criterion_assessments
                            .map(
                                (finding) =>
                                    '<p>' +
                                    escape(finding.result) +
                                    ': ' +
                                    escape(finding.finding) +
                                    '</p>',
                            )
                            .join('') +
                        '</section>'
                    );
                })
                .join('') +
            '</body></html>';
        const url = URL.createObjectURL(
            new Blob([report], { type: 'text/html;charset=utf-8' }),
        );
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download =
            snapshot.request.request_number +
            '-round-' +
            round.round_number +
            '.html';
        anchor.click();
        URL.revokeObjectURL(url);
    }
    return (
        <div className="grid gap-3">
            <p className="font-medium">
                Recommended supplier:{' '}
                {recommended?.quotation_revision.supplier_name_snapshot ??
                    'Not recorded'}
            </p>
            <p className="text-muted-foreground text-sm">
                Frozen {snapshot.generated_at}. The report includes the original
                supplier identity, revision references, component totals,
                findings and decision.
            </p>
            <Button
                type="button"
                variant="outline"
                className="w-fit"
                onClick={downloadReport}
            >
                Download comparison report
            </Button>
        </div>
    );
}
