<?php
declare(strict_types=1);

final class SendgridClient {
	private string $apiKey;
	private string $from;
	private ?string $replyTo = null;

	public function __construct(array $cfg) {
		$this->apiKey = (string)($cfg['sendgrid_api_key'] ?? '');
		$this->from = (string)($cfg['from'] ?? 'no-reply@example.com');
		$rt = trim((string)($cfg['reply_to'] ?? ''));
		$this->replyTo = $rt !== '' ? $rt : null;
	}

	public function send(string $to, string $subject, string $bodyText, array $options = []): array {
		if ($this->apiKey === '') {
			return ['ok' => false, 'status' => 0, 'error' => 'missing_api_key', 'response' => ['message' => 'SENDGRID_API_KEY not set']];
		}
		$url = 'https://api.sendgrid.com/v3/mail/send';
		$payload = [
			'from' => ['email' => $this->from],
			'personalizations' => [[ 'to' => [[ 'email' => $to ]]]],
			'subject' => $subject,
			'content' => [[ 'type' => 'text/plain', 'value' => $bodyText ]],
		];
		$effectiveReplyTo = isset($options['reply_to']) && is_string($options['reply_to']) && $options['reply_to'] !== '' ? (string)$options['reply_to'] : $this->replyTo;
		if ($effectiveReplyTo) { $payload['reply_to'] = ['email' => $effectiveReplyTo]; }
		// Open tracking
		$enableOpen = isset($options['open_tracking']) ? (bool)$options['open_tracking'] : true;
		$payload['tracking_settings'] = [ 'open_tracking' => [ 'enable' => $enableOpen ] ];
		// Custom args for event correlation
		if (!empty($options['custom_args']) && is_array($options['custom_args'])) {
			$payload['custom_args'] = $options['custom_args'];
		}
		$resp = http_request('POST', $url, [
			'headers' => [
				'Authorization: Bearer ' . $this->apiKey,
				'Content-Type: application/json',
			],
			'body' => json_encode($payload, JSON_UNESCAPED_SLASHES),
			'timeout' => 15,
			'retry' => 2,
		]);
		$status = (int)($resp['status'] ?? 0);
		$ok = $status >= 200 && $status < 300;
		return [ 'ok' => $ok, 'status' => $status, 'response' => [ 'body' => $resp['body'] ?? '' ] ];
	}
}
