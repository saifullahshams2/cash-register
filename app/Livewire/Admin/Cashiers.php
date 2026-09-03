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

    #[Rule('required|string|max:255|unique:users,username')]
    public string $username = '';

    #[Rule('required|string|in:admin,cashier')]
    public string $role = User::ROLE_CASHIER;

    #[Rule('required|string|min:6|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function createUser(): void
    {
        $this->validate();

        try {
            $userRole = in_array($this->role, [User::ROLE_ADMIN, User::ROLE_CASHIER], true) ? $this->role : User::ROLE_CASHIER;

            User::create([
                'name' => trim($this->name),
                'username' => trim($this->username),
                'password' => Hash::make($this->password),
                'role' => $userRole,
                'email_verified_at' => now(),
            ]);

            $roleLabel = ucfirst($userRole);
            $this->reset(['name', 'username', 'role', 'password', 'password_confirmation']);
            $this->role = User::ROLE_CASHIER;
            $this->successMessage = "{$roleLabel} account created successfully!";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to create user: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    public function createCashier(): void
    {
        $this->createUser();
    }

    public function deleteUser(int $userId): void
    {
        if (Auth::id() === $userId) {
            $this->errorMessage = 'You cannot delete your own logged-in admin account.';
            $this->successMessage = null;

            return;
        }

        $user = User::find($userId);
        if ($user) {
            $userIdentifier = $user->username ?: ($user->email ?: $user->name);
            $user->delete();
            $this->successMessage = "Account {$user->name} ({$userIdentifier}) deleted successfully.";
            $this->errorMessage = null;
        }
    }

    public function deleteCashier(int $userId): void
    {
        $this->deleteUser($userId);
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
