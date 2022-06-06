<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;



class SearchResultController extends AbstractController
{

    #[Route('/search/result', name: 'app_search_result')]
    public function index(Request $request): Response
    {
        $choose_catalog = ($request->request->all())['state'];

        $items = $this->get_catalog($choose_catalog);

        $catalogs = $items['hits']['hits'];

        return $this->render('search_result/index.html.twig', [
            'catalog_items' => $catalogs,
            'controller_name' => 'SearchResultController',
        ]);
    }

    public function get_catalog(array $select_keys)
    {
        $client = HttpClient::create();

        $response = $client->request('POST', 'http://localhost:9200/catalogs/_search?typed_keys', [
            'auth_basic' => ['elastic', '123123'],
            'json' => [ 
                '_source' => [
                    'suggest-hints',
                    'file-name',
                    'file-url',
                    'file-size'
                ],
                'query' => [
                    'match' => [
                        "suggest-text-content" => implode(" ", $select_keys)
                    ]
                ]
            ]
        ]);

        return $response->toArray();
    }
}
