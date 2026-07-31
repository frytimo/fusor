<?php

use Frytimo\Fusor\resources\attributes\http_get;
use Frytimo\Fusor\resources\classes\fusor_event;

class fusor_activated {
	#[http_get('/*.php', 'after')]
	public static function inject_marker(fusor_event $event): void {
		$data = $event->data;
		$html = (string) ($data['html'] ?? '');
		if (!self::is_html_document($html)) {
			return;
		}

		$marker = '<div style="font-size: 11px; font-family: arial; line-height: 14px; color: rgba(0,0,0,0.1);">Fusor active</div>';
		$body_end = strripos($html, '</body>');
		$html = $body_end === false
			? $html . $marker
			: substr_replace($html, $marker, $body_end, 0);
		$data['html'] = $html;
		$event->data = $data;
	}

	private static function is_html_document(string $document): bool {
		foreach (headers_list() as $header) {
			if (stripos($header, 'Content-Type:') !== 0) {
				continue;
			}

			$content_type = strtolower(trim(explode(';', substr($header, 13), 2)[0]));
			return in_array($content_type, ['text/html', 'application/xhtml+xml'], true);
		}

		return preg_match('/^(?:\xEF\xBB\xBF)?\s*(?:<!doctype\s+html\b|<html\b)/i', $document) === 1;
	}
}
