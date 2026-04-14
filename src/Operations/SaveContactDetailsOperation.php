<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class SaveContactDetailsOperation
{
    /**
     * @param array<string, mixed> $contactDetails
     * @return array{
     *   success: bool,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain, array $contactDetails): array
    {
        $endpoint = '/domain/updateContacts/' . $domain;
        $payload = self::mapWhmcsContactsToApiPayload($contactDetails);
        $response = $client->request('SaveContactDetails', $endpoint, $payload);

        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Save contact details request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'SaveContactDetails',
                    'endpoint' => $endpoint,
                    'payload' => $payload,
                ],
            ];
        }

        return [
            'success' => true,
            'context' => [
                'request' => $response['context'] ?? [],
                'contactTypes' => implode(',', array_keys($contactDetails)),
            ],
            'request' => [
                'operation' => 'SaveContactDetails',
                'endpoint' => $endpoint,
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $contactDetails
     * @return array<string, mixed>
     */
    private static function mapWhmcsContactsToApiPayload(array $contactDetails): array
    {
        $mapping = [
            'Registrant' => 'registrant',
            'Admin' => 'admin',
            'Tech' => 'technical',
            'Billing' => 'billing',
        ];

        $payload = [];
        foreach ($mapping as $whmcsType => $apiType) {
            $contact = isset($contactDetails[$whmcsType]) && is_array($contactDetails[$whmcsType])
                ? $contactDetails[$whmcsType]
                : [];
            $payload[$apiType] = self::mapWhmcsContactToApiContact($contact);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $contact
     * @return array<string, string>
     */
    private static function mapWhmcsContactToApiContact(array $contact): array
    {
        return [
            'firstName' => (string) ($contact['First Name'] ?? ''),
            'lastName' => (string) ($contact['Last Name'] ?? ''),
            'organizationName' => (string) ($contact['Company Name'] ?? ''),
            'email' => (string) ($contact['Email Address'] ?? ''),
            'streetAddress' => (string) ($contact['Address 1'] ?? ''),
            'streetAddress2' => (string) ($contact['Address 2'] ?? ''),
            'city' => (string) ($contact['City'] ?? ''),
            'state' => (string) ($contact['State'] ?? ''),
            'postalCode' => (string) ($contact['Postcode'] ?? ''),
            'country' => (string) ($contact['Country'] ?? ''),
            'phoneNumber' => (string) ($contact['Phone Number'] ?? ''),
        ];
    }
}
