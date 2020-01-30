<?php

namespace Hiraeth\Middleware;

use Hiraeth;

use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

use Psr\Http\Message\UploadedFileInterface as UploadedFile;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

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
		$type = $request->getHeaderLine('Content-Type');

		if (in_array('multipart/form-data', explode(';', $type))) {
			$inputs = [
				'files' => 'UploadedFiles',
				'body'  => 'ParsedBody'
			];

			foreach ($inputs as $method) {
				$request = $request->{'with' . $method}(
					$this->expand($request, $request->{'get' . $method}())
				);
			}
		}

		return $handler->handle($request);
	}


	/**
	 *
	 */
	protected function expand(Request $request, $inputs): array
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
				$head = &$head[$segment];
			}

			$head = $value;
		}

		return $data;
	}
}
