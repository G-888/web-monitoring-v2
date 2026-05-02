<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SslConverterService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SslConversionController extends Controller
{
    protected SslConverterService $converter;

    public function __construct(SslConverterService $converter)
    {
        $this->converter = $converter;
    }

    public function index()
    {
        return view('ssl-conversion');
    }

    public function convert(Request $request)
    {
        $rules = [
            'type' => 'required|in:pem_to_pfx,pfx_to_pem,pem_to_der,der_to_pem',
            'file1' => 'required|file|max:10240',
        ];

        if ($request->type === 'pem_to_pfx') {
            $rules['file2'] = 'required|file|max:10240'; // Private key
            $rules['password'] = 'required|string|min:4';
        } elseif ($request->type === 'pfx_to_pem') {
            $rules['password'] = 'required|string';
        }

        $request->validate($rules);

        try {
            $file1 = $request->file('file1');
            $content1 = file_get_contents($file1->getRealPath());
            
            $output = '';
            $filename = 'converted_cert';
            $contentType = 'application/octet-stream';

            switch ($request->type) {
                case 'pem_to_pfx':
                    $content2 = file_get_contents($request->file('file2')->getRealPath());
                    $output = $this->converter->toPfx($content1, $content2, $request->password);
                    $filename .= '.pfx';
                    $contentType = 'application/x-pkcs12';
                    break;

                case 'pfx_to_pem':
                    $extracted = $this->converter->fromPfx($content1, $request->password);
                    $output = $extracted['cert'] . "\n" . $extracted['key'];
                    if (!empty($extracted['chain'])) {
                        foreach ($extracted['chain'] as $chainCert) {
                            $output .= "\n" . $chainCert;
                        }
                    }
                    $filename .= '.pem';
                    $contentType = 'application/x-pem-file';
                    break;

                case 'pem_to_der':
                    $output = $this->converter->pemToDer($content1);
                    $filename .= '.der';
                    $contentType = 'application/pkix-cert';
                    break;

                case 'der_to_pem':
                    $output = $this->converter->derToPem($content1);
                    $filename .= '.pem';
                    $contentType = 'application/x-pem-file';
                    break;
            }

            return response($output)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            return back()->withErrors(['conversion' => $e->getMessage()])->withInput();
        }
    }
}
