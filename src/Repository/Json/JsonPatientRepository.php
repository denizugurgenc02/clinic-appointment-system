<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\Patient;
use App\Repository\Contract\PatientRepositoryInterface;

final class JsonPatientRepository implements PatientRepositoryInterface
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function find(int $id): ?Patient
    {
        $user = $this->store->find('users', $id);
        $patient = $this->store->find('patients', $id, 'user_id');

        return $user === null || $patient === null ? null : Row::toPatient($user, $patient);
    }

    public function create(Patient $patient): Patient
    {
        $patient->id = $this->store->insert('users', Row::fromUser($patient));
        $this->store->insertRaw('patients', [
            'user_id' => $patient->id,
            'phone' => $patient->phone,
        ]);

        return $patient;
    }

    public function search(?string $term, int $limit = 50): array
    {
        $users = Row::indexBy($this->store->rows('users'));
        $needle = mb_strtolower(trim((string) $term));
        $result = [];

        foreach ($this->store->rows('patients') as $patientRow) {
            $user = $users[Row::int($patientRow, 'user_id')] ?? null;
            if ($user === null) {
                continue;
            }

            $patient = Row::toPatient($user, $patientRow);
            $haystack = mb_strtolower($patient->name . ' ' . $patient->email . ' ' . $patient->phone);

            if ($needle === '' || str_contains($haystack, $needle)) {
                $result[] = $patient;
            }
        }

        usort($result, static fn (Patient $a, Patient $b): int => strcmp($a->name, $b->name));

        return array_slice($result, 0, $limit);
    }
}
