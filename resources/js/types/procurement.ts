export type Choice = { uuid: string; name?: string; legal_name?: string };
export type Currency = { code: string; name: string; minor_digits: number };
export type Item = {
    uuid: string;
    line_number: number;
    description: string;
    specification: string;
    quantity: string;
    unit: string;
};
export type Criterion = {
    uuid: string;
    name: string;
    category: string;
    mandatory: boolean;
    assessment_rule: string;
};
export type Procurement = {
    uuid: string;
    request_number: string;
    title: string;
    description: string | null;
    delivery_destination: string | null;
    currency_code: string | null;
    currency_precision: number | null;
    deadline_at: string | null;
    timezone: string;
    status: string;
    lock_version: number;
    approver_uuid: string | null;
    approver_name: string | null;
    cancellation_reason: string | null;
    items: Item[];
    criteria: Criterion[];
};
export type FileRecord = {
    uuid: string;
    original_name: string;
    scan_status: string;
};
export type Line = {
    uuid: string;
    item_uuid: string;
    unit_price: string | null;
    discount_amount: string | null;
    tax_amount: string | null;
    line_net: string | null;
    line_total: string | null;
    offered_description: string | null;
    supplier_compliance_response: string | null;
    zero_price_reason: string | null;
};
export type Revision = {
    uuid: string;
    revision_number: number;
    status: string;
    supplier_reference: string | null;
    supplier_name_snapshot: string | null;
    currency_code: string;
    valid_until: string | null;
    delivery_days: number | null;
    payment_terms: string | null;
    advance_percent: string | null;
    balance_due_days: number | null;
    delivery_charge: string | null;
    total_amount: string | null;
    submitted_at: string | null;
    submission_key: string | null;
    lock_version: number;
    lines: Line[];
    attachments: FileRecord[];
};
export type Finding = { uuid: string; result: string; finding: string };
export type Candidate = {
    uuid: string;
    disposition: string;
    disposition_reason: string | null;
    revision_uuid: string;
    lock_version: number;
    lines: Finding[];
    criteria: Finding[];
};
export type Round = {
    uuid: string;
    round_number: number;
    status: string;
    recommendation_reason: string | null;
    participation_justification: string | null;
    lock_version: number;
    recommended_candidate_uuid: string | null;
    canAssess: boolean;
    canFreeze: boolean;
    canDecide: boolean;
    candidates: Candidate[];
    approval: {
        uuid: string;
        status: string;
        decision_reason: string | null;
        decided_at: string | null;
        lock_version: number;
    } | null;
    frozen_snapshot: {
        generated_at: string;
        request: { request_number: string; title: string };
        candidates: {
            uuid: string;
            disposition: string;
            disposition_reason: string | null;
            quotation_revision: {
                uuid: string;
                revision_number: number;
                supplier_name_snapshot: string;
                currency_code: string;
                total_amount: string;
                delivery_charge: string;
                delivery_days: number;
                payment_terms: string;
                valid_until: string;
                lines: {
                    unit_price: string;
                    discount_amount: string;
                    line_net: string;
                    tax_amount: string;
                    line_total: string;
                    request_item: {
                        description: string;
                        quantity: string;
                        unit: string;
                    };
                }[];
            };
            line_assessments: { result: string; evidence_note: string }[];
            criterion_assessments: { result: string; finding: string }[];
        }[];
    } | null;
};
export type Participation = {
    uuid: string;
    lock_version: number;
    supplier_uuid: string;
    supplier_name: string;
    status: string;
    quotation_version: number;
};
export type Thread = {
    uuid: string;
    subject: string;
    visibility: string;
    status: string;
    material: boolean;
    lock_version: number;
    messages: {
        uuid: string;
        body: string;
        author: string;
        canAttach: boolean;
        created_at: string;
        attachments: FileRecord[];
    }[];
};
export type PageData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
