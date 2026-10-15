<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AuditLog;
use App\Domain\Admin\Services\AuditService;
use App\Mail\OrderReceivedMail;
use App\Models\User;
use App\Services\TransactionalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class AdminPhase5OperationsTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_owner_can_create_staff_without_leaking_passwords_to_audit(): void
    {
        $owner=User::factory()->create(['role'=>AdminRole::Owner,'password'=>'Owner-pass-123!']);
        $this->actingAs($owner)->post('/admin/staff',[
            'name'=>'Support Agent','email'=>'SUPPORT@EXAMPLE.COM','role'=>'support','disabled'=>false,
            'password'=>'Support-pass-123!','password_confirmation'=>'Support-pass-123!','current_password'=>'Owner-pass-123!',
        ])->assertRedirect();
        $staff=User::where('email','support@example.com')->firstOrFail();
        $this->assertSame(AdminRole::Support,$staff->role);
        $this->assertNotNull($staff->email_verified_at);
        $encoded=json_encode(AuditLog::firstOrFail()->toArray());
        $this->assertStringNotContainsString('Support-pass-123!', $encoded);
        $this->assertStringNotContainsString('Owner-pass-123!', $encoded);
    }

    public function test_non_owner_cannot_manage_staff_or_escalate_privileges(): void
    {
        $admin=User::factory()->create(['role'=>AdminRole::Admin]);
        $support=User::factory()->create(['role'=>AdminRole::Support]);
        $this->actingAs($admin)->patch('/admin/staff/'.$support->id,['name'=>$support->name,'email'=>$support->email,'role'=>'owner','disabled'=>false,'current_password'=>'password'])->assertForbidden();
        $this->assertSame(AdminRole::Support,$support->fresh()->role);
        $legacy=User::factory()->create(['role'=>AdminRole::Customer,'is_admin'=>true]);
        $this->actingAs($legacy)->get('/admin/staff')->assertForbidden();
    }

    public function test_last_owner_cannot_be_disabled_or_demoted_and_customer_is_not_a_staff_target(): void
    {
        $owner=User::factory()->create(['role'=>AdminRole::Owner,'password'=>'Owner-pass-123!']);
        $payload=['name'=>$owner->name,'email'=>$owner->email,'role'=>'admin','disabled'=>false,'current_password'=>'Owner-pass-123!'];
        $this->actingAs($owner)->patch('/admin/staff/'.$owner->id,$payload)->assertSessionHasErrors('staff');
        $customer=User::factory()->create();
        $this->actingAs($owner)->patch('/admin/staff/'.$customer->id,[...$payload,'name'=>$customer->name,'email'=>$customer->email])->assertSessionHasErrors('staff');
        $this->assertSame(AdminRole::Owner,$owner->fresh()->role);
        $this->assertSame(AdminRole::Customer,$customer->fresh()->role);
    }

    public function test_disabled_staff_loses_sessions_and_cannot_log_in(): void
    {
        $owner=User::factory()->create(['role'=>AdminRole::Owner,'password'=>'Owner-pass-123!']);
        $staff=User::factory()->create(['role'=>AdminRole::Support,'password'=>'Support-pass-123!']);
        $this->actingAs($owner)->patch('/admin/staff/'.$staff->id,['name'=>$staff->name,'email'=>$staff->email,'role'=>'support','disabled'=>true,'current_password'=>'Owner-pass-123!'])->assertRedirect();
        $this->post('/logout');
        $this->post('/admin/login',['email'=>$staff->email,'password'=>'Support-pass-123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_audit_is_sanitized_append_only_and_permission_protected(): void
    {
        $owner=User::factory()->create(['role'=>AdminRole::Owner]);
        $support=User::factory()->create(['role'=>AdminRole::Support]);
        $request=request(); $request->setUserResolver(fn()=>$owner);
        app(AuditService::class)->record($request,'security.changed',$owner,['password'=>'x','nested'=>['api_key'=>'y']],['provider_payment_id'=>'z']);
        $log=AuditLog::firstOrFail();
        $this->assertSame('[REDACTED]',$log->before['password']);
        $this->assertSame('[REDACTED]',$log->before['nested']['api_key']);
        $this->assertSame('[REDACTED]',$log->after['provider_payment_id']);
        $this->actingAs($support)->get('/admin/audit')->assertForbidden();
        $this->expectException(LogicException::class); $log->update(['action'=>'tampered']);
    }

    public function test_transactional_mail_is_queued_once_and_only_hashes_recipient(): void
    {
        Mail::fake(); Queue::fake();
        $fixture=$this->checkoutOrder(); $order=$fixture['order'];
        $service=app(TransactionalNotificationService::class);
        $this->assertTrue($service->queueOnce('test:'.$order->id,$order->customer_email,new OrderReceivedMail($order),$order));
        $this->assertFalse($service->queueOnce('test:'.$order->id,$order->customer_email,new OrderReceivedMail($order),$order));
        Mail::assertQueued(OrderReceivedMail::class,1);
        $this->assertDatabaseCount('notification_dispatches',1);
        $this->assertDatabaseMissing('notification_dispatches',['recipient_hash'=>$order->customer_email]);
    }

    public function test_operations_screen_is_server_authorized(): void
    {
        $admin=User::factory()->create(['role'=>AdminRole::Admin]);
        $support=User::factory()->create(['role'=>AdminRole::Support]);
        $this->actingAs($admin)->get('/admin/operations')->assertOk();
        $this->actingAs($support)->get('/admin/operations')->assertForbidden();
    }
}
