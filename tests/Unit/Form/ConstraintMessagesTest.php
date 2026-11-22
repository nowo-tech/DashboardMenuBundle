<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Form;

use Nowo\DashboardMenuBundle\Form\CopyMenuType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

use function count;
use function dirname;
use function is_array;
use function sprintf;

/**
 * Symfony translates constraint messages (`message:`, `mimeTypesMessage:` ...) in the `validators` domain, whatever
 * `translation_domain` the form declares. A dotted key that lives only in `NowoDashboardMenuBundle.<locale>.yaml` would
 * reach the user as raw text. (`buildViolation(...)->setTranslationDomain(...)` is the other, correct way.).
 */
#[CoversNothing]
final class ConstraintMessagesTest extends TestCase
{
    use FormKitMergerTestTrait;

    private const TRANSLATIONS = '/src/Resources/translations';

    /**
     * @return list<string>
     */
    private function shippedLocales(): array
    {
        $locales = [];
        foreach (glob(dirname(__DIR__, 3) . self::TRANSLATIONS . '/NowoDashboardMenuBundle.*.yaml') ?: [] as $file) {
            $locales[] = explode('.', basename($file), 3)[1];
        }
        sort($locales);

        return $locales;
    }

    /**
     * Dotted keys passed as a literal to a constraint option named `message` / `*Message`, in any file under src/.
     * A `buildViolation()` call is not listed: it names its own domain.
     *
     * @return list<string>
     */
    private function dottedConstraintMessages(): array
    {
        $found = [];
        $files = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src')), '/\.php$/');
        foreach ($files as $file) {
            $source = (string) file_get_contents((string) $file);
            if (preg_match_all('/[mM]essage\w*\s*(?::|=>)\s*\'([a-z_0-9]+(?:\.[a-z_0-9]+)+)\'/', $source, $m) > 0) {
                array_push($found, ...$m[1]);
            }
        }

        return array_values(array_unique($found));
    }

    public function testEveryDottedConstraintMessageExistsInValidatorsForEveryShippedLocale(): void
    {
        $messages = $this->dottedConstraintMessages();
        self::assertContains('form.copy_menu_type.code.regex_message', $messages);

        $locales = $this->shippedLocales();
        self::assertGreaterThanOrEqual(30, count($locales));
        foreach ($locales as $locale) {
            $file = dirname(__DIR__, 3) . self::TRANSLATIONS . '/validators.' . $locale . '.yaml';
            self::assertFileExists($file, $locale);
            $flat = self::flatten(Yaml::parseFile($file));

            foreach ($messages as $key) {
                self::assertArrayHasKey($key, $flat, sprintf('%s missing in validators.%s.yaml', $key, $locale));
                self::assertNotSame('', trim((string) $flat[$key]), $key . ' ' . $locale);
            }
        }
    }

    public function testCopyMenuFormShowsTranslatedTextForABadCodeInEveryShippedLocale(): void
    {
        foreach ($this->shippedLocales() as $locale) {
            // The way FrameworkBundle wires it: every catalogue in its own domain, constraint messages in `validators`.
            $translator = new Translator($locale);
            $translator->addLoader('yaml', new YamlFileLoader());
            foreach (['NowoDashboardMenuBundle', 'validators'] as $domain) {
                $file = dirname(__DIR__, 3) . self::TRANSLATIONS . '/' . $domain . '.' . $locale . '.yaml';
                if (is_file($file)) {
                    $translator->addResource('yaml', $file, $locale, $domain);
                }
            }
            $validator = Validation::createValidatorBuilder()->setTranslator($translator)->setTranslationDomain('validators')->getValidator();

            $type = new CopyMenuType();
            $this->injectFormKitMerger($type);
            $form = Forms::createFormFactoryBuilder()
                ->addExtension(new ValidatorExtension($validator))
                ->addType($type)
                ->getFormFactory()
                ->create(CopyMenuType::class);
            $form->submit(['code' => 'bad code!', 'name' => '']);

            $messages = [];
            foreach ($form->getErrors(true) as $error) {
                $messages[] = $error->getMessage();
            }
            self::assertCount(1, $messages, $locale);
            self::assertStringNotContainsString('form.copy_menu_type', $messages[0], $locale);
            self::assertStringNotContainsString('regex_message', $messages[0], $locale);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out += self::flatten($v, $prefix . $k . '.');
            } else {
                $out[$prefix . $k] = $v;
            }
        }

        return $out;
    }
}
