<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repositories;

use App\Modules\Auth\Domain\Models\User;

interface UserRepositoryInterface
{
    public function save(User $user): User;

    public function update(User $user): User;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @return array<User>
     */
    public function findAll(): array;

    public function delete(int $id): void;

    public function count(): int;
}
