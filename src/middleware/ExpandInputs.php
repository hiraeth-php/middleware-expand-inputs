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
		if ($request->getHeaderLine('Content-Type') == 'multipart/form-data') {
			$inputs = [
				'files' => 'UploadedFiles',
				'body'  => 'ParsedBody'
			];

			foreach ($inputs as $input => $method) {
				$$input = array();

				foreach ($request->{'get' . $method}() as $key => $value) {
					if (!strpos($key, '_')) {
						$$input[$key] = $value;
						continue;
					}

					$head = &$$input;

					foreach (explode('_', $key) as $segment) {
						$head = &$head[$segment];
					}

					$head = $value;
				}

				$request = $request->{'with' . $method}($$input);
			}
		}

		return $handler->handle($request);
	}
}
