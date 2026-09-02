<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Cashiers extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Rule('required|string|min:6|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function createCashier(): void
    {
        $this->validate();

        try {
            User::create([
                'name' => trim($this->name),
                'email' => strtolower(trim($this->email)),
                'password' => Hash::make($this->password),
                'role' => User::ROLE_CASHIER,
                'email_verified_at' => now(),
            ]);

            $this->reset(['name', 'email', 'password', 'password_confirmation']);
            $this->successMessage = 'Cashier account created successfully!';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to create cashier: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    public function deleteCashier(int $userId): void
    {
        if (Auth::id() === $userId) {
            $this->errorMessage = 'You cannot delete your own logged-in admin account.';
            $this->successMessage = null;

            return;
        }

        $user = User::find($userId);
        if ($user) {
            $user->delete();
            $this->successMessage = "Account {$user->name} ({$user->email}) deleted successfully.";
            $this->errorMessage = null;
        }
    }

    public function render()
    {
        $users = User::orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('livewire.admin.cashiers', [
            'users' => $users,
        ])->layout('components.layouts.app');
    }
}
