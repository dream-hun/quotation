<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProcurementRole;
use App\Models\Currency;
use App\Models\EvaluationCriterion;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\QuotationRevision;
use App\Models\RequestItem;
use App\Models\Supplier;
use App\Models\SupplierMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ProcurementDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            if (PurchaseRequest::query()->where('request_number', 'RFQ-DEMO-001')->exists()) {
                return;
            }

            $currency = Currency::query()->firstOrCreate(
                ['code' => 'RWF'],
                ['name' => 'Rwandan franc', 'minor_digits' => 0, 'active' => true],
            );

            $organization = Organization::query()->first() ?? Organization::query()->create([
                'name' => 'Demo Buying Organization',
                'timezone' => 'Africa/Kigali',
                'default_currency_code' => $currency->code,
            ]);

            $buyer = User::factory()->create(['name' => 'Demo Buyer', 'email' => 'buyer@procurement.example']);
            $approver = User::factory()->create(['name' => 'Demo Approver', 'email' => 'approver@procurement.example']);
            $buyer->assignRole(ProcurementRole::Buyer);
            $approver->assignRole(ProcurementRole::Approver);

            $request = PurchaseRequest::factory()->create([
                'organization_id' => $organization->id,
                'request_number' => 'RFQ-DEMO-001',
                'title' => 'Office furniture quotation comparison',
                'description' => 'Chairs and desks for the new finance office. Quote delivered and assembled prices.',
                'delivery_destination' => 'Head office, KG 7 Ave, Kigali',
                'currency_code' => $currency->code,
                'currency_precision' => $currency->minor_digits,
                'timezone' => $organization->timezone,
                'created_by' => $buyer->id,
                'assigned_approver_id' => $approver->id,
            ]);

            $chair = RequestItem::factory()->create(['request_id' => $request->id]);
            $desk = RequestItem::factory()->create([
                'request_id' => $request->id,
                'line_number' => 2,
                'description' => 'Office desk',
                'specification' => 'Durable desk with minimum 120 cm working width',
                'quantity' => '5.0000',
            ]);

            foreach (['price', 'delivery', 'payment', 'specification'] as $index => $category) {
                EvaluationCriterion::factory()->create([
                    'request_id' => $request->id,
                    'name' => ucfirst($category),
                    'category' => $category,
                    'mandatory' => $category === 'specification',
                    'assessment_rule' => 'Record and compare the '.$category.' evidence.',
                    'display_order' => $index + 1,
                ]);
            }

            foreach (['Kigali Office Supplies', 'Umucyo Furniture', 'Imena Trading'] as $index => $name) {
                $supplier = Supplier::factory()->create(['legal_name' => $name]);
                $representative = User::factory()->create([
                    'name' => $name.' Representative',
                    'email' => 'supplier'.($index + 1).'@procurement.example',
                ]);
                SupplierMember::factory()->create([
                    'user_id' => $representative->id,
                    'supplier_id' => $supplier->id,
                    'assigned_by' => $buyer->id,
                ]);
                $invitation = Invitation::factory()->create([
                    'request_id' => $request->id,
                    'supplier_id' => $supplier->id,
                    'invited_by' => $buyer->id,
                ]);
                $quotation = Quotation::factory()->create(['invitation_id' => $invitation->id]);
                $revision = QuotationRevision::factory()->create([
                    'quotation_id' => $quotation->id,
                    'prepared_by' => $representative->id,
                ]);

                foreach ([$chair, $desk] as $item) {
                    QuotationLine::factory()->create([
                        'revision_id' => $revision->id,
                        'request_item_id' => $item->id,
                        'unit_price' => null,
                        'discount_amount' => '0.0000',
                        'tax_amount' => '0.0000',
                        'line_net' => null,
                        'line_total' => null,
                    ]);
                }
            }
        });
    }
}
