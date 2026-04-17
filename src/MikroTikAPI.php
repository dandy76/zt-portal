<?php
declare(strict_types=1);

class MikroTikAPI
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $config = Config::getInstance();
        $this->baseUrl = rtrim($config->get('mt_api_url'), '/');
        $this->username = $config->get('mt_api_user');
        $this->password = $config->get('mt_api_pass');
    }

    public function addToAddressList(string $list, string $address, int $timeoutMin, string $comment = ''): bool
    {
        $timeout = sprintf('%02d:%02d:00', intdiv($timeoutMin, 60), $timeoutMin % 60);

        $response = $this->request('PUT', '/ip/firewall/address-list', [
            'list'    => $list,
            'address' => $address,
            'timeout' => $timeout,
            'comment' => $comment,
        ]);

        return $response !== false;
    }

    public function removeFromAddressList(string $list, string $address): bool
    {
        $entries = $this->getAddressListEntries($list, $address);

        if (empty($entries)) {
            return true; // already gone
        }

        $success = true;
        foreach ($entries as $entry) {
            $result = $this->request('DELETE', '/ip/firewall/address-list/' . $entry['.id']);
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    public function refreshAddressListEntry(string $list, string $address, int $timeoutMin, string $comment = ''): bool
    {
        // Remove existing, then re-add with fresh timeout
        $this->removeFromAddressList($list, $address);
        return $this->addToAddressList($list, $address, $timeoutMin, $comment);
    }

    public function getAddressList(string $list): array
    {
        $response = $this->request('GET', '/ip/firewall/address-list', ['list' => $list]);
        return is_array($response) ? $response : [];
    }

    public function isInAddressList(string $list, string $address): bool
    {
        $entries = $this->getAddressListEntries($list, $address);
        return !empty($entries);
    }

    public function getAllDynamicEntries(): array
    {
        $response = $this->request('GET', '/ip/firewall/address-list', ['dynamic' => 'true']);
        return is_array($response) ? $response : [];
    }

    private function getAddressListEntries(string $list, string $address): array
    {
        $response = $this->request('GET', '/ip/firewall/address-list', [
            'list'    => $list,
            'address' => $address,
        ]);
        return is_array($response) ? $response : [];
    }

    private function request(string $method, string $path, array $data = []): mixed
    {
        $url = $this->baseUrl . $path;

        // For GET requests, append query parameters
        if ($method === 'GET' && $data) {
            $queryParts = [];
            foreach ($data as $key => $value) {
                $queryParts[] = '?' . urlencode($key) . '=' . urlencode($value);
            }
            $url .= implode('', $queryParts);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->username . ':' . $this->password,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            AuditLog::log('mikrotik_error', null, json_encode([
                'method' => $method,
                'path' => $path,
                'curl_error' => $error,
            ]));
            return false;
        }

        if ($httpCode >= 400) {
            AuditLog::log('mikrotik_error', null, json_encode([
                'method' => $method,
                'path' => $path,
                'http_code' => $httpCode,
                'response' => $response,
            ]));
            return false;
        }

        $decoded = json_decode($response, true);
        return $decoded ?? $response;
    }

    public function testConnection(): array
    {
        $response = $this->request('GET', '/system/identity');

        if ($response === false) {
            return ['success' => false, 'error' => 'Cannot reach MikroTik API'];
        }

        return ['success' => true, 'identity' => $response['name'] ?? 'Unknown'];
    }
}
