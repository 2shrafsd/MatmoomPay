<?php

declare(strict_types=1);

namespace App\Data;

final readonly class MerchantProfileData
{
    public function __construct(
        public string $name,
        public string $legalName,
        public string $activity,
        public ?string $commercialRegistration,
        public string $email,
        public string $phone,
        public ?string $website,
    ) {}

    /**
     * @param  array{name: string, legal_name: string, activity: string, commercial_registration?: ?string, email: string, phone: string, website?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            legalName: $data['legal_name'],
            activity: $data['activity'],
            commercialRegistration: $data['commercial_registration'] ?? null,
            email: $data['email'],
            phone: $data['phone'],
            website: $data['website'] ?? null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'activity' => $this->activity,
            'commercial_registration' => $this->commercialRegistration,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
        ];
    }
}
