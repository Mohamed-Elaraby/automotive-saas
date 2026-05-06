<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Services\Tenancy\CentralCustomerService;
use App\Services\Tenancy\CentralEmployeeService;
use App\Services\Tenancy\CentralSupplierService;
use Illuminate\Database\Seeder;

class TenantBusinessEntityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customers = app(CentralCustomerService::class);
        $suppliers = app(CentralSupplierService::class);
        $employees = app(CentralEmployeeService::class);

        foreach ($this->demoCustomers() as $customer) {
            $customers->findOrCreate($customer, 'automotive_service', [
                'profile_type' => 'demo',
                'metadata' => ['seeded_by' => static::class],
            ]);
        }

        foreach ($this->demoSuppliers() as $supplier) {
            $suppliers->findOrCreate($supplier, 'automotive_service', [
                'profile_type' => 'demo',
                'metadata' => ['seeded_by' => static::class],
            ]);
        }

        foreach ($this->demoEmployees() as $employee) {
            $employees->findOrCreate($employee, 'automotive_service', [
                'profile_type' => $employee['employee_type'],
                'metadata' => ['seeded_by' => static::class],
            ]);
        }
    }

    protected function demoCustomers(): array
    {
        return [
            ['name' => 'Ahmed Al Mansoori', 'phone' => '+971501110001', 'email' => 'ahmed.customer@example.test', 'customer_type' => 'individual', 'status' => 'active'],
            ['name' => 'Gulf Fleet Services', 'phone' => '+971501110002', 'email' => 'fleet@example.test', 'customer_type' => 'fleet', 'status' => 'active'],
        ];
    }

    protected function demoSuppliers(): array
    {
        return [
            ['name' => 'Abu Dhabi Parts Trading', 'phone' => '+971501220001', 'email' => 'parts@example.test', 'contact_name' => 'Khaled', 'status' => 'active'],
            ['name' => 'Dubai Outsourced Services', 'phone' => '+971501220002', 'email' => 'outsourcing@example.test', 'contact_name' => 'Noura', 'status' => 'active'],
        ];
    }

    protected function demoEmployees(): array
    {
        return [
            ['name' => 'Omar Technician', 'phone' => '+971501330001', 'email' => 'omar.tech@example.test', 'job_title' => 'Senior Technician', 'employee_type' => Employee::TYPE_TECHNICIAN],
            ['name' => 'Sara Advisor', 'phone' => '+971501330002', 'email' => 'sara.advisor@example.test', 'job_title' => 'Service Advisor', 'employee_type' => Employee::TYPE_SERVICE_ADVISOR],
            ['name' => 'Mina Accountant', 'phone' => '+971501330003', 'email' => 'mina.accounting@example.test', 'job_title' => 'Accountant', 'employee_type' => Employee::TYPE_ACCOUNTANT],
        ];
    }
}
