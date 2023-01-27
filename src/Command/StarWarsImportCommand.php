<?php

namespace App\Command;

use App\Entity\Character;
use App\Entity\Movie;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'starwars:import',
    description: 'Import Star Wars character data from an API',
)]
class StarWarsImportCommand extends Command
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Fetch Movie data from API
        $movies = $this->fetchMoviesAPI();
        $this->saveMoviesToDoctrine($movies);

        $characters = $this->fetchCharactersAPI();
        $this->saveCharactersToDoctrine($characters);

        return Command::SUCCESS;
    }

    protected function saveMoviesToDoctrine(array $movies): void
    {
        foreach ( $movies as $movie ) {
            $movieEntity = new Movie();
            $movieEntity->setName($movie['title']);

            $this->em->persist($movieEntity);
        }

        $this->em->flush();
    }

    protected function fetchMoviesAPI(): array
    {
        $client = new Client();
        $response = $client->request('GET', 'https://swapi.dev/api/films/');

        return json_decode($response->getBody()->getContents(), true)['results'];
    }

    public function saveCharactersToDoctrine(array $characters): void
    {
        foreach ($characters as $characterData) {
            $character = new Character();
            $character->setName($characterData['name']);
            $character->setMass((int) $characterData['mass']);
            $character->setHeight((int) $characterData['height']);
            $character->setGender($characterData['gender']);
            $character->setPicture($this->getPicture($characterData['url']));

            foreach ( $characterData['films'] as $film ) {
                // Fetch ID from last part of URL with trailing slash
                $id = (int) substr($film, -2, 1);

                // Fetch all movies from database
                $movies = $this->em->getRepository(Movie::class)->findAll();

                $character->addMovie($movies[$id - 1]);
            }

            $this->em->persist($character);
        }

        $this->em->flush();
    }

    protected function getPicture( string $url ): string {
        // Remove trailing slash
        $url = substr($url, 0, -1);

        $parts = explode('/', $url);

        $id = $parts[count($parts) - 1];

        return sprintf("https://starwars-visualguide.com/assets/img/characters/%d.jpg", $id);
    }

    protected function fetchCharactersAPI( int $currentPage = 1, int $toPage = 3 ): array
    {
        $client = new Client([
            'base_uri' => 'https://swapi.dev/api/',
        ]);

        $characters = [];

        for ($page = $currentPage; $page <= $toPage; $page++) {
            $response = $client->get('people', [
                'query' => [
                    'page' => $page,
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true)['results'];

            $characters = array_merge($characters, $data);
        }

        return $characters;
    }
}
