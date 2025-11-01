<?php
declare(strict_types=1);

final class WhatsappCloud {
	private string $accessToken;
	private string $phoneNumberId;

	public function __construct(array $cfg) {
		$this->accessToken = (string)($cfg['access_token'] ?? '');
		$this->phoneNumberId = (string)($cfg['phone_number_id'] ?? '');
	}

	public function sendText(string $to, string $body): array {
		if ($this->accessToken === '' || $this->phoneNumberId === '') {
			return ['ok' => false, 'status' => 0, 'error' => 'missing_config'];
		}
		$url = 'https://graph.facebook.com/v17.0/' . rawurlencode($this->phoneNumberId) . '/messages';
		$payload = [
			'messaging_product' => 'whatsapp',
			'to' => $to,
			'type' => 'text',
			'text' => [ 'preview_url' => true, 'body' => $body ],
		];
		$resp = http_request('POST', $url, [
			'headers' => [
				'Authorization: Bearer ' . $this->accessToken,
				'Content-Type: application/json',
			],
			'body' => json_encode($payload, JSON_UNESCAPED_SLASHES),
			'timeout' => 15,
			'retry' => 2,
		]);
		$status = (int)($resp['status'] ?? 0);
		$json = json_decode($resp['body'] ?? '', true);
		if (!is_array($json)) $json = [];
		$messageId = null;
		if (isset($json['messages'][0]['id'])) $messageId = (string)$json['messages'][0]['id'];
		log_message_event($messageId, 'whatsapp', 'meta', 'send', $status, [ 'to' => $to, 'body' => $body ], $json);
		return [ 'ok' => $status >= 200 && $status < 300, 'status' => $status, 'response' => $json, 'provider_message_id' => $messageId ];
	}
}
