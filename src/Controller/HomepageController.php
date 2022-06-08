<?php

namespace App\Controller;

use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;

class HomepageController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function index(): Response
    {
        return $this->render('homepage/index.html.twig', [
            'controller_name' => 'HomepageController',
        ]);
    }

    private $targetDirectory;
    private $slugger;

    #[Route('/preload', name: 'app_preload')]
    public function preload(Request $request): Response
    {
        return new Response (
            "<!DOCTYPE html>
            <html>
                <head>
                    <meta charset=\"utf-8\">
                    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
                    <title>learn select2</title>
                    <link rel=\"icon\" href=\"./assets/favicon.ico\" type=\"image/x-icon\">
                    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css\" rel=\"stylesheet\" integrity=\"sha384-0evHe/X+R7YkIZDRvuzKMRqM+OrBnVFBL6DOitfPri4tjfHxaWutUpFmBp4vmVor\" crossorigin=\"anonymous\">
                    <style>
                        .container-div {
                            margin-left: 33%;
                            margin-right: 33%;
                        }
                        div + div {
                            margin-top: 2%;
                        }
                    </style>
                </head>
                <body class='p-3'>
                    <div class='container-div'>
                        <form action='./api/download' method='POST' enctype=\"multipart/form-data\">
                            <div>
                                <input class='form-control' type='file' name='download'>
                            </div>
                            <div style='width: 100%; text-align:center;'>
                                <button class='btn btn-secondary' type='submit'>Загрузить</button>
                            </div>
                        </form>
                    </div>
                </body>
            </html>"
        );
    }

    #[Route('/api/search/hints', name: 'app_search_hints')]
    public function search_hints(Request $request): JsonResponse
    {
        if (!empty($request->toArray()))
        {
            $items = $this->get_hint($request->toArray()['search']);

            if (!empty($items['suggest']['phrase#song-suggest'][0]['options']))
                return new JsonResponse($items['suggest']['phrase#song-suggest'][0]['options']);
            else
                return new JsonResponse($items['suggest']['phrase#song-suggest']);
        }
    }

    #[Route('/api/search/highlight', name: 'app_search_highlight')]
    public function search(Request $request): JsonResponse
    {
        if (!empty($request->toArray()))
        {
            $items = $this->get_highlight($request->toArray()['search']);

            if (!empty($items['suggest']['phrase#highlight-suggest']))
                return new JsonResponse($items['suggest']['phrase#highlight-suggest']);
            else
                return new JsonResponse($items['suggest']['phrase#highlight-suggest']);
        }
    }

    #[Route('/api/download', name: 'app_download')]
    public function download_file(Request $request, FileUploader $fileUploader): JsonResponse
    {
        $parser = new \Smalot\PdfParser\Parser();

        $file = $request->files->get('download');

        $filename = $fileUploader->upload($file); //upload: return filename

        $host = $request->server->get('HTTP_ORIGIN'); // get current server url: <http://example.com:0000/>

        $upload = "/uploads/".$filename; // way to file

        $pdf_obj = $parser->parseFile(".".$upload); // return data about pdf file
        
        $text = explode("\n", $pdf_obj->getText());

        $elastic_content_array = [];

        $rep = ["\u003A", "\u2022", "\u000b", "\u25a0", "\u00005", "\u001e"];

        for ($i = 0; $i < 21; $i++)
        { 
            static $is = "\u0000";
            array_push($rep, $is);
            $is++;
        }

        foreach ($text as $key => $value) {
            if (json_encode($value)) // test string encode
            {
                if (!empty($value))
                {
                    $json = json_encode($value);
                    $json = str_replace($rep, " zebl ", $json);
                    array_push($elastic_content_array, str_replace("zebl", "", json_decode($json)));
                }
            }
        }  

        $response = $this->push_pdf( 
            [
                'file-name' => explode(".pdf", $file->getClientOriginalName())[0],
                'file-url' => $host.$upload,
                'file-size' => $_FILES['download']['size'], // filesize($file),
            ],
            $elastic_content_array // implode(" ", $elastic_content_array)
        );

        if ($response)
            return new JsonResponse(['file' => $filename, 'message' => 'upload successesful!']);
        else
            return new JsonResponse(['file' => $filename, 'message' => 'upload error.']);
    }

    public function push_pdf(array $file, array $elastic_content) {

        $client = HttpClient::create();

        $response = $client->request('POST', 'http://localhost:9200/catalogs/_doc', [
            'auth_basic' => ['elastic', '123123'],
            'json' => [ 
                'suggest-completion' => $elastic_content,
                'suggest-keywords' => $elastic_content,
                'suggest-string' => $elastic_content,
                'suggest-hints' => implode(" ", $elastic_content),
                'suggest-text-content' => implode(" ", $elastic_content),
                'file-name' => $file['file-name'],
                'file-url' => $file['file-url'],
                'file-size' => $file['file-size']           
            ]
        ]);

        return $response;
    }

    public function get_highlight(string $text)
    {
        $client = HttpClient::create();

        $response = $client->request('POST', 'http://localhost:9200/catalogs/_search?typed_keys', [
            'auth_basic' => ['elastic', '123123'],
            'json' => [
                "suggest" => [
                    "highlight-suggest" => [
                        "prefix" => $text, // Поиск на основании совпадения параметра "score"
                        "phrase" => [
                            "field" => "suggest-hints",
                            "highlight" => [
                                "pre_tag" => "<highlight>",
                                "post_tag" => "<highlight>"
                            ]
                        ]
                    ]
                ] 
            ]
        ]);

        return $response->toArray();
    }

    public function get_hint(string $text) // Old
    {
        $client = HttpClient::create();

        $response = $client->request('POST', 'http://localhost:9200/catalogs/_search?typed_keys', [
            'auth_basic' => ['elastic', '123123'],
            'json' => [
                "suggest" => [
                    "song-suggest" => [
                        "prefix" => $text,
                        "phrase" => [
                            "field" => "suggest-hints"
                        ]
                    ]
                ] 
            ]
        ]);

        return $response->toArray();
    }
}
