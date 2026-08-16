<?php

use App\Enums\ApplicationStatus;
use App\Enums\MediaType;
use App\Enums\SettingKey;
use App\Enums\TechnicianSource;
use App\Enums\TechnicianStatus;
use App\Models\Admin;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Specialization;
use App\Models\Technician;
use App\Models\TechnicianApplication;
use App\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * The submission endpoint is public and takes nine images, so the phone gate,
 * the on/off switch and the file cleanup all have to hold without a session.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->governorate = Governorate::create(['name' => 'بغداد', 'is_active' => true]);
    $this->district = District::create([
        'governorate_id' => $this->governorate->id, 'name' => 'الكرخ', 'is_active' => true,
    ]);
    $other = Governorate::create(['name' => 'البصرة', 'is_active' => true]);
    $this->otherDistrict = District::create([
        'governorate_id' => $other->id, 'name' => 'الزبير', 'is_active' => true,
    ]);
    $this->specialization = Specialization::create(['name' => 'كهربائي', 'is_active' => true]);

    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;
});

function payload(array $overrides = []): array
{
    $image = fn () => UploadedFile::fake()->image('doc.png', 40, 40);

    return array_merge([
        'full_name' => 'حسين عبد الرزاق جاسم',
        'phone' => '07712345678',
        'governorate_id' => test()->governorate->id,
        'district_id' => test()->district->id,
        'specialization_ids' => [test()->specialization->id],
        MediaType::PERSONAL->value => $image(),
        MediaType::ID_FRONT->value => $image(),
        MediaType::ID_BACK->value => $image(),
        MediaType::RESIDENCE_FRONT->value => $image(),
        MediaType::RESIDENCE_BACK->value => $image(),
        'work_samples' => [$image(), $image()],
    ], $overrides);
}

function submit(array $overrides = [])
{
    return test()->postJson('/api/v1/customer/technician-application', payload($overrides));
}

it('accepts a complete application from a visitor with no account', function () {
    submit()
        ->assertCreated()
        ->assertJsonPath('message', 'شكراً على إرسال طلبك، سيتم الاتصال بك');

    $application = TechnicianApplication::sole();

    expect($application->status)->toBe(ApplicationStatus::PENDING)
        ->and($application->phone)->toBe('9647712345678')
        ->and($application->media)->toHaveCount(7)
        ->and($application->specializations)->toHaveCount(1);
});

it('refuses a second application from the same phone whatever its status', function (string $status) {
    submit()->assertCreated();
    TechnicianApplication::sole()->forceFill(['status' => $status])->save();

    submit(['full_name' => 'اسم آخر تماماً'])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'تم التسجيل مسبقاً بهذا الرقم');

    expect(TechnicianApplication::count())->toBe(1);
})->with(['pending', 'under_review', 'rejected']);

it('refuses a phone that already belongs to a technician', function () {
    Technician::create([
        'name' => 'علي', 'phone' => '9647712345678',
        'governorate_id' => $this->governorate->id, 'district_id' => $this->district->id,
        'status' => TechnicianStatus::ACTIVE,
    ]);

    submit()->assertStatus(422)->assertJsonPath('errors.phone.0', 'هذا الرقم مسجّل كفني بالفعل');
});

it('refuses a district that does not belong to the chosen governorate', function () {
    submit(['district_id' => $this->otherDistrict->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.district_id.0', 'اختر قضاءً يتبع المحافظة المختارة');
});

it('demands a full three-part name', function () {
    submit(['full_name' => 'حسين جاسم'])
        ->assertStatus(422)
        ->assertJsonPath('errors.full_name.0', 'اكتب الاسم الثلاثي كاملاً');
});

it('demands every official document', function () {
    submit([MediaType::RESIDENCE_BACK->value => null])
        ->assertStatus(422)
        ->assertJsonPath('errors.residence_back.0', 'ظهر بطاقة السكن مطلوبة');
});

it('caps the work samples at four', function () {
    $image = fn () => UploadedFile::fake()->image('s.png', 40, 40);

    submit(['work_samples' => [$image(), $image(), $image(), $image(), $image()]])
        ->assertStatus(422)
        ->assertJsonPath('errors.work_samples.0', 'الحد الأقصى 4 نماذج أعمال');
});

it('leaves no file behind when the submission is rejected', function () {
    submit(['full_name' => 'حسين جاسم'])->assertStatus(422);

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(TechnicianApplication::count())->toBe(0);
});

it('hides and refuses the form while the switch is off', function () {
    app(SettingService::class)->set(SettingKey::TECHNICIAN_APPLICATION_OPEN, '0');

    $this->getJson('/api/v1/customer/technician-application')
        ->assertOk()
        ->assertJsonPath('data.is_open', false);

    submit()->assertStatus(422)->assertJsonPath('errors.form.0', 'التسجيل كفني مغلق حالياً');
});

it('opens the form by default with nothing saved', function () {
    $this->getJson('/api/v1/customer/technician-application')
        ->assertOk()
        ->assertJsonPath('data.is_open', true);
});

it('turns an accepted application into a pending technician carrying its files', function () {
    submit()->assertCreated();
    $application = TechnicianApplication::sole();

    expect(collect(Storage::disk('local')->allFiles())->filter(fn ($p) => str_starts_with($p, 'applications/')))
        ->toHaveCount(7);

    $this->actingAs($this->admin, 'admin')
        ->postJson("/api/v1/admin/technician-applications/{$application->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.technician.source', 'application');

    $technician = Technician::sole();

    expect($technician->status)->toBe(TechnicianStatus::PENDING)
        ->and($technician->source)->toBe(TechnicianSource::APPLICATION)
        ->and($technician->phone)->toBe('9647712345678')
        ->and($technician->media()->count())->toBe(7)
        ->and($technician->specializations()->count())->toBe(1);

    // The files moved rather than being copied or re-uploaded.
    $paths = collect(Storage::disk('local')->allFiles());

    expect($paths->filter(fn ($p) => str_starts_with($p, "technicians/{$technician->id}/")))->toHaveCount(7)
        ->and($paths->filter(fn ($p) => str_starts_with($p, 'applications/')))->toBeEmpty();
});

it('leaves nothing of the application behind once it is accepted', function () {
    submit()->assertCreated();
    $id = TechnicianApplication::sole()->id;

    $this->actingAs($this->admin, 'admin')
        ->postJson("/api/v1/admin/technician-applications/{$id}/accept")->assertOk();

    expect(TechnicianApplication::count())->toBe(0)
        ->and(DB::table('technician_application_media')->count())->toBe(0)
        ->and(DB::table('technician_application_specialization')->count())->toBe(0);

    $this->actingAs($this->admin, 'admin')
        ->getJson("/api/v1/admin/technician-applications/{$id}")->assertNotFound();
});

it('still blocks the phone after acceptance, now as a technician', function () {
    submit()->assertCreated();

    $this->actingAs($this->admin, 'admin')
        ->postJson('/api/v1/admin/technician-applications/'.TechnicianApplication::sole()->id.'/accept')->assertOk();

    submit()->assertStatus(422)->assertJsonPath('errors.phone.0', 'هذا الرقم مسجّل كفني بالفعل');
});

it('refuses to reject without a reason', function () {
    submit()->assertCreated();

    $this->actingAs($this->admin, 'admin')
        ->patchJson('/api/v1/admin/technician-applications/'.TechnicianApplication::sole()->id.'/status', ['status' => 'rejected'])
        ->assertStatus(422)
        ->assertJsonPath('errors.note.0', 'سبب الرفض مطلوب');
});

it('keeps the images when the application row is consumed by acceptance', function () {
    submit()->assertCreated();

    $this->actingAs($this->admin, 'admin')
        ->postJson('/api/v1/admin/technician-applications/'.TechnicianApplication::sole()->id.'/accept')->assertOk();

    foreach (Technician::sole()->media as $media) {
        expect(Storage::disk('local')->exists($media->path))->toBeTrue();
    }
});

it('frees the phone again once the rejected application is deleted', function () {
    submit()->assertCreated();
    TechnicianApplication::sole()->forceFill(['status' => ApplicationStatus::REJECTED])->save();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson('/api/v1/admin/technician-applications/'.TechnicianApplication::sole()->id)
        ->assertOk();

    expect(Storage::disk('local')->allFiles())->toBeEmpty();

    submit()->assertCreated();
});

it('keeps the submission endpoint closed to nobody', function () {
    $this->getJson('/api/v1/customer/technician-application')->assertOk();
});

it('lets an admin delete a rejected application and wipes its files off disk', function () {
    submit()->assertCreated();
    $application = TechnicianApplication::first();
    $application->forceFill(['status' => ApplicationStatus::REJECTED])->save();

    expect(Storage::disk('local')->allFiles())->not->toBeEmpty();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/technician-applications/{$application->id}")
        ->assertOk()
        ->assertJsonPath('message', 'تم حذف الاستمارة ومرفقاتها');

    expect(TechnicianApplication::find($application->id))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('refuses to delete an application that is not rejected', function (string $status) {
    submit()->assertCreated();
    $application = TechnicianApplication::first();
    $application->forceFill(['status' => $status])->save();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/technician-applications/{$application->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'لا يمكن حذف إلا الاستمارات المرفوضة');

    expect(TechnicianApplication::find($application->id))->not->toBeNull()
        ->and(Storage::disk('local')->allFiles())->not->toBeEmpty();
})->with(['pending', 'under_review']);

/**
 * Identity papers must never sit on the web-served disk, and the link that
 * shows them must expire and be unforgeable — an <img> the whole world can
 * open with a copied URL is exactly what this guards against.
 */
it('serves application documents only through a signed link, never a public path', function () {
    submit()->assertCreated();
    $application = TechnicianApplication::first();

    // Nothing landed on the public disk; everything is on the private one.
    expect(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and(Storage::disk('local')->allFiles())->not->toBeEmpty();

    $documents = $this->actingAs($this->admin, 'admin')
        ->getJson("/api/v1/admin/technician-applications/{$application->id}")
        ->assertOk()
        ->json('data.documents');

    $url = $documents['id_front']['url'];

    expect($url)->toContain('/admin/media')
        ->and($url)->toContain('signature=')
        ->and($url)->not->toContain('/storage/');
});

it('rejects an unsigned or tampered media link', function () {
    submit()->assertCreated();
    $path = TechnicianApplication::first()->media()->first()->path;

    // No signature at all.
    $this->getJson('/api/v1/admin/media?path='.urlencode($path))->assertStatus(403);

    // A valid signature for one path cannot be pointed at another.
    $signed = URL::temporarySignedRoute('admin.media', now()->addMinutes(30), ['path' => $path]);
    $swapped = str_replace(urlencode($path), urlencode('applications/999/forged.png'), $signed);
    $this->get($swapped)->assertStatus(403);
});

it('refuses to serve a file outside the document trees even when signed', function () {
    $signed = URL::temporarySignedRoute('admin.media', now()->addMinutes(30), ['path' => '../../.env']);
    $this->get($signed)->assertStatus(404);
});
