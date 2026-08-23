<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Issuer;
use Tests\TestCase;

class CustomerRegistryTest extends TestCase
{
    public function test_index_requires_customers_view(): void
    {
        $account = $this->createStaffAccount();

        $this->actingAs($account)
            ->get('/customers')
            ->assertForbidden();
    }

    public function test_index_lists_customers_with_search(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.view']);

        Customer::factory()->create([
            'doc_type' => Customer::DOC_RUC,
            'doc_number' => '20512333797',
            'name' => 'COMERCIAL ANDINA SAC',
        ]);
        Customer::factory()->create([
            'doc_type' => Customer::DOC_DNI,
            'doc_number' => '12345678',
            'name' => 'María Quispe Huamán',
        ]);

        $this->actingAs($account)
            ->get('/customers?q=2051')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Customers/Index')
                    ->where('customers.total', 1)
                    ->where('filters.q', '2051'),
            );

        $this->actingAs($account)
            ->get('/customers?q=mar%C3%ADa')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('customers.total', 1));
    }

    public function test_store_requires_customers_manage(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.view']);

        $this->actingAs($account)
            ->post('/customers', $this->customerPayload())
            ->assertForbidden();
    }

    public function test_store_creates_ruc_customer(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.manage']);

        $this->actingAs($account)
            ->post('/customers', $this->customerPayload(
                docType: Customer::DOC_RUC,
                docNumber: '20512333797',
                name: 'COMERCIAL ANDINA SAC',
            ))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'doc_type' => Customer::DOC_RUC,
            'doc_number' => '20512333797',
            'name' => 'COMERCIAL ANDINA SAC',
        ]);
    }

    public function test_store_rejects_ruc_with_bad_check_digit(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.manage']);

        $this->actingAs($account)
            ->post('/customers', $this->customerPayload(
                docType: Customer::DOC_RUC,
                docNumber: '20512333798',
            ))
            ->assertSessionHasErrors('doc_number');
    }

    public function test_store_rejects_dni_with_wrong_length(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.manage']);

        $this->actingAs($account)
            ->post('/customers', $this->customerPayload(docNumber: '1234567'))
            ->assertSessionHasErrors('doc_number');
    }

    public function test_store_rejects_duplicate_document_with_friendly_error(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.manage']);

        $issuer = Issuer::query()->sole();
        Customer::factory()->forIssuer($issuer)->create([
            'doc_type' => Customer::DOC_DNI,
            'doc_number' => '12345678',
        ]);

        $this->actingAs($account)
            ->post('/customers', $this->customerPayload(docNumber: '12345678'))
            ->assertSessionHasErrors('doc_number');
    }

    public function test_final_consumer_identity_cannot_be_created_manually(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.manage']);

        $this->actingAs($account)
            ->post('/customers', $this->customerPayload(
                docType: Customer::DOC_OTHER,
                docNumber: '00000000',
                name: 'Consumidor final',
            ))
            ->assertSessionHasErrors('doc_number');
    }

    public function test_final_consumer_row_cannot_be_edited(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.manage']);

        $issuer = Issuer::query()->sole();
        $finalConsumer = Customer::factory()->forIssuer($issuer)->create([
            'doc_type' => Customer::DOC_OTHER,
            'doc_number' => Customer::FINAL_CONSUMER_DOC_NUMBER,
            'name' => 'Consumidor final',
        ]);

        $this->actingAs($account)
            ->put("/customers/{$finalConsumer->code}", $this->customerPayload(name: 'Otro nombre'))
            ->assertSessionHasErrors('doc_type');
    }

    public function test_update_edits_customer_identity(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['customers.manage']);

        $customer = Customer::factory()->create([
            'doc_type' => Customer::DOC_DNI,
            'doc_number' => '12345678',
            'name' => 'María Quispe',
        ]);

        $this->actingAs($account)
            ->put("/customers/{$customer->code}", $this->customerPayload(
                docType: Customer::DOC_RUC,
                docNumber: '20512333797',
                name: 'COMERCIAL ANDINA SAC',
            ))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'code' => $customer->code,
            'doc_type' => Customer::DOC_RUC,
            'doc_number' => '20512333797',
        ]);
    }

    /**
     * @return array{doc_type: string, doc_number: string, name: string}
     */
    private function customerPayload(
        string $docType = Customer::DOC_DNI,
        string $docNumber = '12345678',
        string $name = 'María Quispe Huamán',
    ): array {
        return [
            'doc_type' => $docType,
            'doc_number' => $docNumber,
            'name' => $name,
        ];
    }
}
