<?php

namespace Hiraeth\Middleware;

use Hiraeth;
use Hiraeth\Http;

use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

use Psr\Http\Message\UploadedFileInterface as UploadedFile;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

/**
 *
 */
class ExpandInputs implements Middleware
{
	/**
	 *
	 */
	public function process(Request $request, Handler $handler): Response
	{
		$type    = $request->getHeaderLine('content-type');
		$request = $request->withQueryParams(
			$this->expand($request, $request->getQueryParams())
		);

		$request = match (TRUE) {
			str_contains($type, 'application/x-www-form-urlencoded')
				=> $this->processUrlEncoded($request),
			str_contains($type, 'multipart/form-data')
				=> $this->processMultipart($request),
			str_contains($type, 'application/json')
				=> $this->processJson($request),
			default
				=> $request
		};

		return $handler->handle($request);
	}


	/**
	 *
	 */
	public function processJson(Request $request): Request
	{
		if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
			$parsed = json_decode($request->getBody()->getContents(), TRUE);

			if (is_array($parsed)) {
				$request = $request->withParsedBody($parsed);
			}
		}

		return $request;
	}


	/**
	 *
	 */
	public function processMultipart(Request $request): Request
	{
		if (in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'])) {
			if (function_exists('request_parse_body')) {
				$parsed  = request_parse_body();
				$request = $request->withParsedBody($this->expand($parsed[0]));

			} else {
				throw new RuntimeException(sprintf(
					'Method "%s" does not support type "%s" request on PHP < 8.4',
					$request->getMethod(),
					$request->getHeaderLine('content-type')
				));
			}

			// TODO: Handle Uploaded Files.

		} else {
			$request = $request
				->withParsedBody($this->expand($request->getParsedBody()))
				->withUploadedFiles($this->expand($request->getUploadedFiles()))
			;

		}

		return $request;
	}


	/**
	 *
	 */
	public function processUrlEncoded(Request $request): Request
	{
		$parsed = array();

		if (in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'])) {
			if (function_exists('mb_parse_str')) {
				mb_parse_str($request->getBody()->getContents(), $parsed);
			} else {
				parse_str($request->getBody()->getContents(), $parsed);
			}

			$request = $request->withParsedBody($this->expand($parsed));

		} else {
			$request = $request
				->withParsedBody($this->expand($request->getParsedBody()))
			;

		}

		return $request;
	}



	/**
	 * @param array<string, mixed> $inputs
	 * @return array<string, mixed>
	 */
	protected function expand($inputs): array
	{
		$data = array();

		foreach ($inputs as $key => $value) {
			if ($value instanceof UploadedFile && $value->getError() == UPLOAD_ERR_NO_FILE) {
				continue;
			}

			if (!strpos($key, '_')) {
				$data[$key] = $value;
				continue;
			}

			$head = &$data;

			foreach (explode('_', $key) as $segment) {
				if (!is_array($head)) {
					settype($head, 'array');
				}

				$head = &$head[$segment];
			}

			if (isset($value[0]) && in_array($value[0], ['{', '[', '"'])) {
				$value = json_decode($value);
			}

			$head = $value;
		}

		return $data;
	}
}
