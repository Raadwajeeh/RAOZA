<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class FoundationTest extends TestCase { use RefreshDatabase; public function test_home_page_is_available(): void { $this->get('/')->assertOk(); } public function test_admin_requires_authentication(): void { $this->get('/admin')->assertRedirect('/admin/login'); } }
