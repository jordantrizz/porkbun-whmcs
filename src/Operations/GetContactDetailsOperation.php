<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class GetContactDetailsOperation
{
    /**
     * @return array{
     *   success: bool,
     *   contacts?: array<string, array<string, string>>,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain): array
    {
        $endpoint = '/domain/getContacts/' . $domain;
        $response = $client->request('GetContactDetails', $endpoint, []);

        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Get contact details request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'GetContactDetails',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $contacts = self::extractContacts($data);

        return [
            'success' => true,
            'contacts' => $contacts,
            'context' => [
                'request' => $response['context'] ?? [],
                'contactTypes' => implode(',', array_keys($contacts)),
            ],
            'request' => [
                'operation' => 'GetContactDetails',
                'endpoint' => $endpoint,
                'payload' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<string, string>>
     */
    private static function extractContacts(array $data): array
    {
        $base = is_array($data['contacts'] ?? null) ? $data['contacts'] : $data;

        $types = [
            'Registrant' => ['registrant', 'owner', 'contact'],
            'Admin' => ['admin', 'administrative'],
            'Tech' => ['tech', 'technical'],
            'Billing' => ['billing'],
        ];

        $result = [];
        foreach ($types as $whmcsType => $keys) {
            $contact = null;
            foreach ($keys as $key) {
                if (isset($base[$key]) && is_array($base[$key])) {
                    $contact = $base[$key];
                    break;
                }
            }

            if ($contact === null && is_array($base)) {
                $contact = $base;
            }

            $result[$whmcsType] = self::mapContactToWhmcs($contact ?? []);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $contact
     * @return array<string, string>
     */
    private static function mapContactToWhmcs(array $contact): array
    {
        return [
            'First Name' => (string) ($contact['firstName'] ?? $contact['firstname'] ?? ''),
            'Last Name' => (string) ($contact['lastName'] ?? $contact['lastname'] ?? ''),
            'Company Name' => (string) ($contact['company'] ?? $contact['organizationName'] ?? ''),
            'Email Address' => (string) ($contact['email'] ?? ''),
            'Address 1' => (string) ($contact['address1'] ?? $contact['streetAddress'] ?? ''),
            'Address 2' => (string) ($contact['address2'] ?? ''),
            'City' => (string) ($contact['city'] ?? ''),
            'State' => (string) ($contact['state'] ?? ''),
            'Postcode' => (string) ($contact['postalCode'] ?? $contact['zipcode'] ?? ''),
            'Country' => (string) ($contact['country'] ?? ''),
            'Phone Number' => (string) ($contact['phone'] ?? $contact['phoneNumber'] ?? ''),
        ];
    }
}
