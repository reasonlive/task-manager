<?php

namespace App\Views;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class View
{
    private Environment $twig;

    public function __construct(string $templatePath)
    {
        $loader = new FilesystemLoader($templatePath);
        $this->twig = new Environment($loader, [
            //'error_reporting' => E_ALL & ~E_WARNING, // или E_ERROR для полного отключения
            'cache' => false, // В продакшене установите путь к кэшу
            'debug' => true,
        ]);

        // Добавляем пользовательские функции
        $this->twig->addFunction(new TwigFunction('asset', function ($path) {
            return '/public/' . ltrim($path, '/');
        }));
    }

    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }

    public function display(string $template, array $data = []): void
    {
        echo $this->render($template, $data);
    }
}