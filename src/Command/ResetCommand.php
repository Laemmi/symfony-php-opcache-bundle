<?php

/**
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    symfony-php-opcache-bundle
 * @author     Michael Lämmlein <laemmi@spacerabbit.de>
 * @copyright  ©2020 laemmi
 * @license    http://www.opensource.org/licenses/mit-license.php MIT-License
 * @version    1.0.0
 * @since      14.09.20
 */

declare(strict_types=1);

namespace Laemmi\Bundle\PhpOpcacheBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpClient\HttpClient;

class ResetCommand extends Command
{
    private string $projectDir;

    protected static $defaultName = 'phpopcache:reset';

    public function __construct(string $name = null, KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('Reset the phpopcache with http request')
            ->addArgument('url', InputArgument::REQUIRED, 'Url e.g. http://localhost:8000')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = $input->getArgument('url');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $io->error(sprintf('Wrong url %s', $url));
            return 0;
        }

        $filename = realpath($this->projectDir . '/public') . '/' . uniqid() . '.php';

        if (false === file_put_contents($filename, '<?php opcache_reset(); ?>')) {
            $io->error('Can not generate file');
            return 0;
        }

        $io->note(sprintf('Generate file %s', $filename));

        try {
            $client = HttpClient::create();
            $response = $client->request('GET', $url . '/' . basename($filename));
            $statusCode = $response->getStatusCode();
        } catch (\Exception $e) {
            unlink($filename);
            $io->error($e->getMessage());
            return 0;
        }

        unlink($filename);

        if (200 === $statusCode) {
            $io->success('Gratulation you have reset opcache');
        } else {
            $io->error(sprintf('Statuscode %s', $statusCode));
        }

        return 0;
    }
}
