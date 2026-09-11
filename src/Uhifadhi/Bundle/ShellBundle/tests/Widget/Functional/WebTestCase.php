<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\HostUser;

/**
 * A library page, a real database, and a real person signed in — which a widget
 * library needs before it is anything at all, since every layout it renders and
 * every write it takes belongs to somebody.
 */
abstract class WebTestCase extends KernelTestCase
{
    /** The area every layout here is remembered against. */
    protected const string AREA = '0192f7a0-0000-7000-8000-000000000000';

    protected EntityManagerInterface $em;

    private ?KernelBrowser $browser = null;

    protected static function getKernelClass(): string
    {
        return WebKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->em->clear();
    }

    /** The person whose dashboard this is, signed in through the real firewall. */
    protected function signIn(): HostUser
    {
        $user = new HostUser();
        $user->setEmail('ranger@example.org')->setFirstName('Ada')->setLastName('Ranger');
        $this->em->persist($user);
        $this->em->flush();

        $this->browser()->loginUser($user);

        return $user;
    }

    /**
     * ONE CLIENT PER TEST. `test.client` is defined shared:false, so asking the
     * container twice hands back two browsers and the second has never made the
     * request whose response the assertion wants.
     */
    protected function browser(): KernelBrowser
    {
        if (null === $this->browser) {
            /** @var KernelBrowser $client */
            $client = static::getContainer()->get('test.client');
            $this->browser = $client;
        }

        return $this->browser;
    }

    protected function get(string $path): Response
    {
        $this->browser()->request('GET', $path);

        return $this->browser()->getResponse();
    }

    protected function body(string $path): string
    {
        return (string) $this->get($path)->getContent();
    }

    /**
     * A plain form post, exactly as the page's own <form> makes it.
     *
     * @param array<string, string> $fields
     */
    protected function post(string $path, string $token, array $fields = []): Response
    {
        $this->browser()->request('POST', $path, ['_token' => $token] + $fields);

        return $this->browser()->getResponse();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            new SchemaTool($this->em)->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
            $this->em->close();
        }

        parent::tearDown();

        // The debug error handler is registered during the test and never
        // popped; PHPUnit flags that as risky. Pop whatever is left.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
