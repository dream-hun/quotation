<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private int $guardNumber = 0;

    public function up(): void
    {
        throw_unless(in_array(DB::getDriverName(), ['mysql', 'sqlite'], true), RuntimeException::class, 'Procurement integrity constraints support MySQL and SQLite tests.');

        $this->rejectWhen('organizations', ['INSERT', 'UPDATE'], 'one_buying_organization', <<<'SQL'
NEW.singleton_key <> 1
SQL);

        $this->rejectWhen('currencies', ['INSERT', 'UPDATE'], 'currency_precision_range', <<<'SQL'
NEW.minor_digits < 0 OR NEW.minor_digits > 4
SQL);

        $this->rejectWhen('users', ['INSERT', 'UPDATE'], 'nonempty_normalized_email', <<<'SQL'
length(trim(NEW.email)) = 0
SQL);

        $this->rejectWhen('request_items', ['INSERT', 'UPDATE'], 'positive_item_quantity', <<<'SQL'
NEW.quantity <= 0 OR NEW.line_number <= 0
SQL);

        $this->rejectWhen('evaluation_criteria', ['INSERT', 'UPDATE'], 'positive_criterion_order', <<<'SQL'
NEW.display_order <= 0
SQL);

        $this->rejectWhen('purchase_requests', ['INSERT', 'UPDATE'], 'request_precision_range', <<<'SQL'
NEW.currency_precision < 0 OR NEW.currency_precision > 4
SQL);

        $this->rejectWhen('purchase_requests', ['INSERT', 'UPDATE'], 'independent_request_approver', <<<'SQL'
NEW.created_by = NEW.assigned_approver_id
SQL);

        $this->rejectWhen('purchase_requests', ['INSERT', 'UPDATE'], 'request_not_its_own_clone', <<<'SQL'
NEW.cloned_from_id = NEW.id
SQL);

        $this->rejectWhen('quotations', ['INSERT', 'UPDATE'], 'withdrawal_actor_and_time', <<<'SQL'
(NEW.withdrawn_at IS NULL AND NEW.withdrawn_by IS NOT NULL) OR (NEW.withdrawn_at IS NOT NULL AND NEW.withdrawn_by IS NULL)
SQL);

        $this->rejectWhen('quotation_revisions', ['INSERT', 'UPDATE'], 'positive_revision_number', <<<'SQL'
NEW.revision_number <= 0
SQL);

        $this->rejectWhen('quotation_revisions', ['INSERT', 'UPDATE'], 'valid_commercial_amounts', <<<'SQL'
NEW.delivery_days < 0 OR NEW.balance_due_days < 0 OR NEW.advance_percent < 0 OR NEW.advance_percent > 100 OR NEW.delivery_charge < 0 OR NEW.total_amount < 0
SQL);

        $this->rejectWhen('quotation_revisions', ['INSERT', 'UPDATE'], 'submitted_revision_complete', <<<'SQL'
NEW.status = 'submitted' AND (NEW.supplier_name_snapshot IS NULL OR NEW.currency_code IS NULL OR NEW.valid_until IS NULL OR NEW.delivery_days IS NULL OR NEW.payment_terms IS NULL OR NEW.delivery_charge IS NULL OR NEW.total_amount IS NULL OR NEW.calculation_version IS NULL OR NEW.submitted_by IS NULL OR NEW.submitted_at IS NULL OR NEW.submission_key IS NULL OR length(trim(NEW.payment_terms)) = 0)
SQL);

        $this->rejectWhen('quotation_revisions', ['INSERT', 'UPDATE'], 'submitted_currency_matches_request', <<<'SQL'
NEW.status = 'submitted' AND NOT EXISTS (SELECT 1 FROM quotations q JOIN invitations i ON i.id = q.invitation_id JOIN purchase_requests r ON r.id = i.request_id WHERE q.id = NEW.quotation_id AND r.currency_code = NEW.currency_code)
SQL);

        $this->rejectWhen('quotation_lines', ['INSERT', 'UPDATE'], 'nonnegative_line_money', <<<'SQL'
NEW.unit_price < 0 OR NEW.discount_amount < 0 OR NEW.tax_amount < 0 OR NEW.line_net < 0 OR NEW.line_total < 0
SQL);

        $this->rejectWhen('quotation_lines', ['INSERT', 'UPDATE'], 'line_matches_request', <<<'SQL'
NOT EXISTS (SELECT 1 FROM quotation_revisions v JOIN quotations q ON q.id = v.quotation_id JOIN invitations i ON i.id = q.invitation_id JOIN request_items ri ON ri.request_id = i.request_id WHERE v.id = NEW.revision_id AND ri.id = NEW.request_item_id)
SQL);

        $this->rejectWhen('quotation_lines', ['INSERT', 'UPDATE'], 'discount_within_extension', <<<'SQL'
NEW.discount_amount > NEW.unit_price * (SELECT quantity FROM request_items WHERE id = NEW.request_item_id)
SQL);

        $this->rejectWhen('evaluation_rounds', ['INSERT', 'UPDATE'], 'positive_round_number', <<<'SQL'
NEW.round_number <= 0
SQL);

        $this->rejectWhen('evaluation_rounds', ['INSERT', 'UPDATE'], 'recommendation_matches_round', <<<'SQL'
NEW.recommended_candidate_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM evaluation_candidates c WHERE c.id = NEW.recommended_candidate_id AND c.round_id = NEW.id)
SQL);

        $this->rejectWhen('evaluation_rounds', ['INSERT', 'UPDATE'], 'frozen_recommendation_complete', <<<'SQL'
NEW.status = 'frozen' AND (NEW.recommended_candidate_id IS NULL OR NEW.recommendation_reason IS NULL OR length(trim(NEW.recommendation_reason)) = 0 OR NEW.frozen_snapshot IS NULL OR NEW.calculation_version IS NULL OR NEW.submitted_at IS NULL OR NOT EXISTS (SELECT 1 FROM evaluation_candidates c WHERE c.id = NEW.recommended_candidate_id AND c.disposition = 'eligible'))
SQL);

        $this->rejectWhen('evaluation_candidates', ['INSERT', 'UPDATE'], 'candidate_matches_request', <<<'SQL'
NOT EXISTS (SELECT 1 FROM quotation_revisions v JOIN quotations q ON q.id = v.quotation_id JOIN invitations i ON i.id = q.invitation_id JOIN evaluation_rounds r ON r.request_id = i.request_id WHERE v.id = NEW.quotation_revision_id AND v.status = 'submitted' AND r.id = NEW.round_id)
SQL);

        $this->rejectWhen('evaluation_candidates', ['INSERT', 'UPDATE'], 'one_revision_per_candidate_supplier', <<<'SQL'
EXISTS (SELECT 1 FROM evaluation_candidates c JOIN quotation_revisions v ON v.id = c.quotation_revision_id JOIN quotation_revisions n ON n.quotation_id = v.quotation_id WHERE c.round_id = NEW.round_id AND c.id <> NEW.id AND n.id = NEW.quotation_revision_id)
SQL, ['evaluation_rounds', 'NEW.round_id']);

        $this->rejectWhen('evaluation_candidates', ['INSERT', 'UPDATE'], 'excluded_candidate_has_reason', <<<'SQL'
NEW.disposition = 'excluded' AND (NEW.disposition_reason IS NULL OR length(trim(NEW.disposition_reason)) = 0)
SQL);

        $this->rejectWhen('line_assessments', ['INSERT', 'UPDATE'], 'line_assessment_matches_revision', <<<'SQL'
NOT EXISTS (SELECT 1 FROM evaluation_candidates c JOIN quotation_lines l ON l.revision_id = c.quotation_revision_id WHERE c.id = NEW.candidate_id AND l.id = NEW.quotation_line_id)
SQL);

        $this->rejectWhen('criterion_assessments', ['INSERT', 'UPDATE'], 'criterion_assessment_matches_request', <<<'SQL'
NOT EXISTS (SELECT 1 FROM evaluation_candidates c JOIN evaluation_rounds r ON r.id = c.round_id JOIN evaluation_criteria ec ON ec.request_id = r.request_id WHERE c.id = NEW.candidate_id AND ec.id = NEW.criterion_id)
SQL);

        $this->rejectWhen('approval_cases', ['INSERT', 'UPDATE'], 'approval_requires_frozen_round', <<<'SQL'
NOT EXISTS (SELECT 1 FROM evaluation_rounds r JOIN purchase_requests p ON p.id = r.request_id WHERE r.id = NEW.round_id AND r.status = 'frozen' AND r.prepared_by <> NEW.assigned_approver_id AND p.created_by <> NEW.assigned_approver_id)
SQL);

        $this->rejectWhen('approval_cases', ['INSERT', 'UPDATE'], 'approval_decision_shape', <<<'SQL'
(NEW.status = 'pending' AND (NEW.decided_by IS NOT NULL OR NEW.decided_at IS NOT NULL)) OR (NEW.status <> 'pending' AND (NEW.decided_by IS NULL OR NEW.decided_at IS NULL OR NEW.decided_by <> NEW.assigned_approver_id)) OR (NEW.status IN ('returned', 'rejected') AND (NEW.decision_reason IS NULL OR length(trim(NEW.decision_reason)) = 0))
SQL);

        $this->rejectWhen('awards', ['INSERT', 'UPDATE'], 'award_matches_approved_evidence', <<<'SQL'
NOT EXISTS (SELECT 1 FROM approval_cases a JOIN evaluation_rounds r ON r.id = a.round_id JOIN evaluation_candidates c ON c.id = r.recommended_candidate_id JOIN quotation_revisions v ON v.id = c.quotation_revision_id WHERE a.id = NEW.approval_case_id AND a.status = 'approved' AND r.status = 'frozen' AND r.request_id = NEW.request_id AND v.id = NEW.quotation_revision_id AND v.total_amount = NEW.approved_total AND v.currency_code = NEW.currency_code AND a.decided_by = NEW.awarded_by)
SQL);

        $this->rejectWhen('awards', ['INSERT', 'UPDATE'], 'nonnegative_award_amount', <<<'SQL'
NEW.approved_total < 0
SQL);

        $this->rejectWhen('clarification_threads', ['INSERT', 'UPDATE'], 'thread_visibility_scope', <<<'SQL'
(NEW.visibility = 'all_invited' AND (NEW.supplier_id IS NOT NULL OR NEW.quotation_revision_id IS NOT NULL)) OR (NEW.visibility = 'private' AND (NEW.supplier_id IS NULL OR NOT EXISTS (SELECT 1 FROM invitations i WHERE i.request_id = NEW.request_id AND i.supplier_id = NEW.supplier_id)))
SQL);

        $this->rejectWhen('clarification_threads', ['INSERT', 'UPDATE'], 'thread_revision_scope', <<<'SQL'
NEW.quotation_revision_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM quotation_revisions v JOIN quotations q ON q.id = v.quotation_id JOIN invitations i ON i.id = q.invitation_id WHERE v.id = NEW.quotation_revision_id AND i.request_id = NEW.request_id AND i.supplier_id = NEW.supplier_id)
SQL);

        $this->rejectWhen('clarification_threads', ['INSERT', 'UPDATE'], 'thread_resolution_shape', <<<'SQL'
(NEW.status = 'resolved' AND (NEW.resolved_by IS NULL OR NEW.resolved_at IS NULL)) OR (NEW.status = 'open' AND (NEW.resolved_by IS NOT NULL OR NEW.resolved_at IS NOT NULL))
SQL);

        $this->rejectWhen('attachments', ['INSERT', 'UPDATE'], 'positive_attachment_size', <<<'SQL'
NEW.size_bytes <= 0
SQL);

        $this->rejectWhen('procurement_notifications', ['INSERT', 'UPDATE'], 'nonnegative_email_attempts', <<<'SQL'
NEW.email_attempts < 0
SQL);

        $this->rejectWhen('user_roles', ['INSERT', 'UPDATE'], 'internal_roles_exclude_supplier_membership', <<<'SQL'
EXISTS (SELECT 1 FROM supplier_members m WHERE m.user_id = NEW.user_id AND m.status = 'active')
SQL, ['users', 'NEW.user_id']);

        $this->rejectWhen('supplier_members', ['INSERT', 'UPDATE'], 'supplier_membership_excludes_internal_roles', <<<'SQL'
NEW.status = 'active' AND EXISTS (SELECT 1 FROM user_roles r WHERE r.user_id = NEW.user_id)
SQL, ['users', 'NEW.user_id']);

        $this->rejectWhen('currencies', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('organizations', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('user_roles', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.user_id __same__ OLD.user_id) OR NOT (NEW.assigned_by __same__ OLD.assigned_by)
SQL);

        $this->rejectWhen('suppliers', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('supplier_members', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.user_id __same__ OLD.user_id) OR NOT (NEW.supplier_id __same__ OLD.supplier_id) OR NOT (NEW.assigned_by __same__ OLD.assigned_by)
SQL);

        $this->rejectWhen('supplier_members', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('purchase_requests', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.organization_id __same__ OLD.organization_id) OR NOT (NEW.created_by __same__ OLD.created_by) OR NOT (NEW.cloned_from_id __same__ OLD.cloned_from_id)
SQL);

        $this->rejectWhen('purchase_requests', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('request_items', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.request_id __same__ OLD.request_id)
SQL);

        $this->rejectWhen('request_items', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('evaluation_criteria', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.request_id __same__ OLD.request_id)
SQL);

        $this->rejectWhen('evaluation_criteria', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('invitations', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.request_id __same__ OLD.request_id) OR NOT (NEW.supplier_id __same__ OLD.supplier_id) OR NOT (NEW.invited_by __same__ OLD.invited_by)
SQL);

        $this->rejectWhen('invitations', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('quotations', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.invitation_id __same__ OLD.invitation_id)
SQL);

        $this->rejectWhen('quotations', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('quotation_revisions', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.quotation_id __same__ OLD.quotation_id) OR NOT (NEW.prepared_by __same__ OLD.prepared_by)
SQL);

        $this->rejectWhen('quotation_revisions', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('quotation_lines', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.revision_id __same__ OLD.revision_id) OR NOT (NEW.request_item_id __same__ OLD.request_item_id)
SQL);

        $this->rejectWhen('quotation_lines', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('evaluation_rounds', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.request_id __same__ OLD.request_id) OR NOT (NEW.prepared_by __same__ OLD.prepared_by)
SQL);

        $this->rejectWhen('evaluation_rounds', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('evaluation_candidates', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.round_id __same__ OLD.round_id) OR NOT (NEW.quotation_revision_id __same__ OLD.quotation_revision_id)
SQL);

        $this->rejectWhen('evaluation_candidates', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('line_assessments', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.candidate_id __same__ OLD.candidate_id) OR NOT (NEW.quotation_line_id __same__ OLD.quotation_line_id) OR NOT (NEW.assessed_by __same__ OLD.assessed_by)
SQL);

        $this->rejectWhen('line_assessments', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('criterion_assessments', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.candidate_id __same__ OLD.candidate_id) OR NOT (NEW.criterion_id __same__ OLD.criterion_id) OR NOT (NEW.assessed_by __same__ OLD.assessed_by)
SQL);

        $this->rejectWhen('criterion_assessments', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('approval_cases', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.round_id __same__ OLD.round_id)
SQL);

        $this->rejectWhen('approval_cases', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('awards', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.request_id __same__ OLD.request_id) OR NOT (NEW.approval_case_id __same__ OLD.approval_case_id) OR NOT (NEW.quotation_revision_id __same__ OLD.quotation_revision_id) OR NOT (NEW.awarded_by __same__ OLD.awarded_by)
SQL);

        $this->rejectWhen('clarification_threads', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.request_id __same__ OLD.request_id) OR NOT (NEW.supplier_id __same__ OLD.supplier_id) OR NOT (NEW.quotation_revision_id __same__ OLD.quotation_revision_id) OR NOT (NEW.opened_by __same__ OLD.opened_by)
SQL);

        $this->rejectWhen('clarification_threads', ['INSERT', 'UPDATE'], 'positive_lock_version', <<<'SQL'
NEW.lock_version < 1
SQL);

        $this->rejectWhen('clarification_messages', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.thread_id __same__ OLD.thread_id) OR NOT (NEW.author_id __same__ OLD.author_id)
SQL);

        $this->rejectWhen('attachments', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.uploaded_by __same__ OLD.uploaded_by)
SQL);

        $this->rejectWhen('request_attachments', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.attachment_id __same__ OLD.attachment_id) OR NOT (NEW.request_id __same__ OLD.request_id)
SQL);

        $this->rejectWhen('revision_attachments', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.attachment_id __same__ OLD.attachment_id) OR NOT (NEW.revision_id __same__ OLD.revision_id)
SQL);

        $this->rejectWhen('message_attachments', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.attachment_id __same__ OLD.attachment_id) OR NOT (NEW.message_id __same__ OLD.message_id)
SQL);

        $this->rejectWhen('procurement_notifications', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.recipient_id __same__ OLD.recipient_id) OR NOT (NEW.request_id __same__ OLD.request_id)
SQL);

        $this->rejectWhen('audit_events', ['UPDATE'], 'record_parent_is_stable', <<<'SQL'
NOT (NEW.request_id __same__ OLD.request_id) OR NOT (NEW.actor_user_id __same__ OLD.actor_user_id)
SQL);

        $this->rejectWhen('awards', ['UPDATE', 'DELETE'], 'append_only_record', <<<'SQL'
1 = 1
SQL);

        $this->rejectWhen('audit_events', ['UPDATE', 'DELETE'], 'append_only_record', <<<'SQL'
1 = 1
SQL);

        $this->rejectWhen('clarification_messages', ['UPDATE', 'DELETE'], 'append_only_record', <<<'SQL'
1 = 1
SQL);

        $this->rejectWhen('request_attachments', ['UPDATE', 'DELETE'], 'append_only_record', <<<'SQL'
1 = 1
SQL);

        $this->rejectWhen('revision_attachments', ['UPDATE', 'DELETE'], 'append_only_record', <<<'SQL'
1 = 1
SQL);

        $this->rejectWhen('message_attachments', ['UPDATE', 'DELETE'], 'append_only_record', <<<'SQL'
1 = 1
SQL);

        $this->rejectWhen('quotation_revisions', ['UPDATE', 'DELETE'], 'submitted_revision_is_immutable', <<<'SQL'
OLD.status = 'submitted'
SQL);

        $this->rejectWhen('evaluation_rounds', ['UPDATE', 'DELETE'], 'frozen_round_is_immutable', <<<'SQL'
OLD.status = 'frozen'
SQL);

        $this->rejectWhen('approval_cases', ['UPDATE', 'DELETE'], 'decided_approval_is_immutable', <<<'SQL'
OLD.status <> 'pending'
SQL);

        $this->rejectWhen('quotation_lines', ['INSERT', 'UPDATE'], 'submitted_revision_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM quotation_revisions v WHERE v.id = NEW.revision_id AND v.status = 'submitted')
SQL);

        $this->rejectWhen('quotation_lines', ['DELETE'], 'submitted_revision_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM quotation_revisions v WHERE v.id = OLD.revision_id AND v.status = 'submitted')
SQL);

        $this->rejectWhen('revision_attachments', ['INSERT', 'UPDATE'], 'submitted_revision_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM quotation_revisions v WHERE v.id = NEW.revision_id AND v.status = 'submitted')
SQL);

        $this->rejectWhen('revision_attachments', ['DELETE'], 'submitted_revision_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM quotation_revisions v WHERE v.id = OLD.revision_id AND v.status = 'submitted')
SQL);

        $this->rejectWhen('evaluation_candidates', ['INSERT', 'UPDATE'], 'frozen_evaluation_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM evaluation_rounds r WHERE r.id = NEW.round_id AND r.status = 'frozen')
SQL);

        $this->rejectWhen('evaluation_candidates', ['DELETE'], 'frozen_evaluation_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM evaluation_rounds r WHERE r.id = OLD.round_id AND r.status = 'frozen')
SQL);

        $this->rejectWhen('line_assessments', ['INSERT', 'UPDATE'], 'frozen_evaluation_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM evaluation_candidates c JOIN evaluation_rounds r ON r.id = c.round_id WHERE c.id = NEW.candidate_id AND r.status = 'frozen')
SQL);

        $this->rejectWhen('line_assessments', ['DELETE'], 'frozen_evaluation_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM evaluation_candidates c JOIN evaluation_rounds r ON r.id = c.round_id WHERE c.id = OLD.candidate_id AND r.status = 'frozen')
SQL);

        $this->rejectWhen('criterion_assessments', ['INSERT', 'UPDATE'], 'frozen_evaluation_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM evaluation_candidates c JOIN evaluation_rounds r ON r.id = c.round_id WHERE c.id = NEW.candidate_id AND r.status = 'frozen')
SQL);

        $this->rejectWhen('criterion_assessments', ['DELETE'], 'frozen_evaluation_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM evaluation_candidates c JOIN evaluation_rounds r ON r.id = c.round_id WHERE c.id = OLD.candidate_id AND r.status = 'frozen')
SQL);

        $this->rejectWhen('purchase_requests', ['UPDATE'], 'published_request_scope_is_immutable', <<<'SQL'
OLD.published_at IS NOT NULL AND (NOT (NEW.title __same__ OLD.title) OR NOT (NEW.description __same__ OLD.description) OR NOT (NEW.delivery_destination __same__ OLD.delivery_destination) OR NOT (NEW.currency_code __same__ OLD.currency_code) OR NOT (NEW.currency_precision __same__ OLD.currency_precision) OR NOT (NEW.timezone __same__ OLD.timezone) OR NOT (NEW.organization_id __same__ OLD.organization_id) OR NOT (NEW.created_by __same__ OLD.created_by) OR NOT (NEW.request_number __same__ OLD.request_number) OR NOT (NEW.published_at __same__ OLD.published_at))
SQL);

        $this->rejectWhen('purchase_requests', ['DELETE'], 'published_request_is_retained', <<<'SQL'
OLD.published_at IS NOT NULL
SQL);

        $this->rejectWhen('request_items', ['INSERT', 'UPDATE'], 'published_request_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM purchase_requests r WHERE r.id = NEW.request_id AND r.published_at IS NOT NULL)
SQL);

        $this->rejectWhen('request_items', ['DELETE'], 'published_request_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM purchase_requests r WHERE r.id = OLD.request_id AND r.published_at IS NOT NULL)
SQL);

        $this->rejectWhen('evaluation_criteria', ['INSERT', 'UPDATE'], 'published_request_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM purchase_requests r WHERE r.id = NEW.request_id AND r.published_at IS NOT NULL)
SQL);

        $this->rejectWhen('evaluation_criteria', ['DELETE'], 'published_request_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM purchase_requests r WHERE r.id = OLD.request_id AND r.published_at IS NOT NULL)
SQL);

        $this->rejectWhen('request_attachments', ['INSERT', 'UPDATE'], 'published_request_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM purchase_requests r WHERE r.id = NEW.request_id AND r.published_at IS NOT NULL)
SQL);

        $this->rejectWhen('request_attachments', ['DELETE'], 'published_request_children_are_immutable', <<<'SQL'
EXISTS (SELECT 1 FROM purchase_requests r WHERE r.id = OLD.request_id AND r.published_at IS NOT NULL)
SQL);

        $this->rejectWhen('clarification_threads', ['UPDATE'], 'private_conversations_cannot_be_published', <<<'SQL'
NEW.visibility <> OLD.visibility
SQL);

        $this->rejectWhen('request_attachments', ['INSERT', 'UPDATE'], 'attachment_has_only_one_owner', <<<'SQL'
EXISTS (SELECT 1 FROM revision_attachments a WHERE a.attachment_id = NEW.attachment_id) OR EXISTS (SELECT 1 FROM message_attachments a WHERE a.attachment_id = NEW.attachment_id)
SQL, ['attachments', 'NEW.attachment_id']);

        $this->rejectWhen('revision_attachments', ['INSERT', 'UPDATE'], 'attachment_has_only_one_owner', <<<'SQL'
EXISTS (SELECT 1 FROM request_attachments a WHERE a.attachment_id = NEW.attachment_id) OR EXISTS (SELECT 1 FROM message_attachments a WHERE a.attachment_id = NEW.attachment_id)
SQL, ['attachments', 'NEW.attachment_id']);

        $this->rejectWhen('message_attachments', ['INSERT', 'UPDATE'], 'attachment_has_only_one_owner', <<<'SQL'
EXISTS (SELECT 1 FROM request_attachments a WHERE a.attachment_id = NEW.attachment_id) OR EXISTS (SELECT 1 FROM revision_attachments a WHERE a.attachment_id = NEW.attachment_id)
SQL, ['attachments', 'NEW.attachment_id']);

        $this->rejectWhen('attachments', ['UPDATE'], 'linked_file_content_is_immutable', <<<'SQL'
(EXISTS (SELECT 1 FROM request_attachments a WHERE a.attachment_id = OLD.id) OR EXISTS (SELECT 1 FROM revision_attachments a WHERE a.attachment_id = OLD.id) OR EXISTS (SELECT 1 FROM message_attachments a WHERE a.attachment_id = OLD.id)) AND (NOT (NEW.storage_key __same__ OLD.storage_key) OR NOT (NEW.original_name __same__ OLD.original_name) OR NOT (NEW.detected_media_type __same__ OLD.detected_media_type) OR NOT (NEW.size_bytes __same__ OLD.size_bytes) OR NOT (NEW.content_hash __same__ OLD.content_hash) OR NOT (NEW.uploaded_by __same__ OLD.uploaded_by) OR NOT (NEW.uploaded_at __same__ OLD.uploaded_at))
SQL);
    }

    /**
     * @param  literal-string  $table
     * @param  list<literal-string>  $events
     * @param  literal-string  $reason
     * @param  literal-string  $condition
     * @param  array{0: literal-string, 1: literal-string}|null  $parentLock
     */
    private function rejectWhen(string $table, array $events, string $reason, string $condition, ?array $parentLock = null): void
    {
        $this->guardNumber++;
        $mysql = DB::getDriverName() === 'mysql';
        $condition = str_replace('__same__', $mysql ? '<=>' : 'IS', $condition);

        foreach ($events as $event) {
            $trigger = $table.'_guard_'.$this->guardNumber.'_'.mb_strtolower($event);

            if ($mysql) {
                $lock = $parentLock === null
                    ? ''
                    : "DECLARE locked_parent BIGINT; SELECT id INTO locked_parent FROM {$parentLock[0]} WHERE id = {$parentLock[1]} FOR UPDATE;";

                DB::statement("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN {$lock} IF ({$condition}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$reason}'; END IF; END");
            } else {
                DB::statement("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW WHEN ({$condition}) BEGIN SELECT RAISE(ABORT, '{$reason}'); END");
            }
        }
    }
};
