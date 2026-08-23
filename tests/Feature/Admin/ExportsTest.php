<?php

use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\User;

test('exports are staff only', function () {
    foreach (['loans', 'reservations', 'incidents'] as $type) {
        $this->actingAs(User::factory()->create())
            ->get("/admin/export/{$type}")
            ->assertForbidden();
    }
});

test('the loans export contains loan data as csv', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create(['matric_no' => 'PMS777001']);
    $type = EquipmentType::factory()->create(['name' => 'Netballs']);

    Loan::factory()->withItem($type, 2)->for($student)->create();

    $response = $this->actingAs($staff)->get('/admin/export/loans');

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('student,matric_no')
        ->toContain('PMS777001')
        ->toContain('2x Netballs');
});

test('unknown export types are not found', function () {
    $this->actingAs(User::factory()->jskkStaff()->create())
        ->get('/admin/export/bogus')
        ->assertNotFound();
});
