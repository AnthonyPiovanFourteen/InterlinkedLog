<?php

namespace App\Application\UseCases\Auth;

use App\Domain\Entities\Company;
use App\Domain\Entities\User;
use App\Domain\Repositories\CompanyRepository;
use App\Domain\Repositories\UserRepository;

class RegisterUserUseCase
{
    public function __construct(
        private UserRepository $userRepository,
        private CompanyRepository $companyRepository,
    ) {}

    public function execute(array $data): array
    {
        if ($this->userRepository->findByEmail($data['email'])) {
            throw new \DomainException('Email já cadastrado');
        }

        $company = $this->companyRepository->findById($data['company_id']);
        if (!$company) {
            $company = Company::create(uuid_create(), $data['company_name'], $data['company_cnpj'] ?? '');
            $this->companyRepository->save($company);
        }

        $user = User::create(
            id: uuid_create(),
            name: $data['name'],
            email: $data['email'],
            passwordHash: password_hash($data['password'], PASSWORD_BCRYPT),
            role: $data['role'] ?? 'Usuário',
            companyId: $company->id,
        );

        $this->userRepository->save($user);

        return [
            'user' => $user,
            'company' => $company,
        ];
    }
}
