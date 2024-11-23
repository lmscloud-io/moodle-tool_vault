<?php
// This file is part of plugin tool_vault - https://lmsvault.io
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Help functions for CLI scripts
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault\local;

use tool_vault\api;
use tool_vault\constants;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/clilib.php');

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

/**
 * Help functions for CLI scripts
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cli_helper {
    /** @var int */
    const OUTPUTWIDTH = 120;

    /** @var string */
    const SCRIPT_BACKUP = 'backup';

    /** @var string script name, one of the SCRIPT* constants above */
    protected $script;
    /** @var string Name of php file containing the caller script (for printing help) */
    protected $scriptfilename;
    /** @var string|null */
    protected $extrahelp;
    /** @var array */
    protected $clioptions;

    /**
     * cli_helper constructor.
     *
     * @param string $script script name, one of the SCRIPT* constants above
     * @param string $scriptfilename Name of php file containing the caller script (for printing help)
     * @param string|null $extrahelp Additional information to add to the help text after peramters and before example
     */
    public function __construct($script, $scriptfilename, $extrahelp = null) {
        $this->script = $script;
        $this->scriptfilename = $scriptfilename;
        $this->extrahelp = $extrahelp;
        $optionsdefinitions = $this->options_definitions();
        $longoptions = [];
        $shortmapping = [];
        foreach ($optionsdefinitions as $key => $option) {
            $longoptions[$key] = isset($option['default']) ? $option['default'] : null;
            if (!empty($option['alias'])) {
                $shortmapping[$option['alias']] = $key;
            }
        }

        list($this->clioptions, $unrecognized) = cli_get_params(
            $longoptions,
            $shortmapping
        );
    }

    /**
     * Options used in this CLI script
     *
     * @return array
     */
    public function options_definitions() {
        global $CFG;
        $options = [
            'help' => [
                'hasvalue' => false,
                'description' => 'Print out this help',
                'default' => 0,
                'alias' => 'h',
            ],
        ];
        $options += [
            'apikey' => [
                'hasvalue' => 'APIKEY',
                'description' => 'API key, unless already specified in the Vault settings',
                'validation' => function($apikey) {
                    $apikey = empty($apikey) ? api::get_api_key() : $apikey;
                    if (empty($apikey)) {
                        $this->cli_error('Argument --apikey is required');
                    } else if (!api::validate_api_key($apikey)) {
                        $this->cli_error('API key not valid');
                    }
                },
            ],
        ];
        if ($this->script === self::SCRIPT_BACKUP) {
            $options += [
                'description' => [
                    'description' => 'Backup description, by default - site URL',
                    'hasvalue' => 'TEXT',
                    'default' => $CFG->wwwroot,
                    'validation' => function($text) {
                        if ($text !== clean_param($text, PARAM_TEXT)) {
                            $this->cli_error('Backup description can not contain HTML');
                        } else if (strlen($text) > constants::DESCRIPTION_MAX_LENGTH) {
                            $this->cli_error('Description should not be longer than '.
                                constants::DESCRIPTION_MAX_LENGTH.' characters');
                        }
                    },
                ],
            ];
        }

        if ($this->script === self::SCRIPT_BACKUP) {
            $dryrundescription = 'Check only, do not backup';
            $options += [
                'passphrase' => [
                    'description' => 'Passphrase to use for encryption',
                    'hasvalue' => 'PHRASE',
                ],
                'dryrun' => [
                    'description' => $dryrundescription,
                    'hasvalue' => false,
                ],
            ];
            $options += [
                'storage' => [
                    'description' => 'Storage for the backup (if supported in your subscription)',
                    'hasvalue' => 'NAME',
                ],
            ];
            $options += [
                'expiredays' => [
                    'description' => 'Days before backup expires',
                    'hasvalue' => 'NUMBER',
                    'validation' => function($text) {
                        if (''.$text !== '' && $text !== ''.clean_param($text, PARAM_INT)) {
                            $this->cli_error('Parameter expiredays must be a number');
                        }
                    },
                ],
            ];
        }
        return $options;
    }

    /**
     * Print help for export
     */
    public function print_help() {
        $titles = [
            self::SCRIPT_BACKUP => 'Command line site backup',
        ];
        $this->cli_writeln($titles[$this->script]);
        $this->cli_writeln('');
        $this->print_help_options($this->options_definitions());

        if ($this->extrahelp) {
            $this->cli_writeln('');
            $this->cli_writeln(wordwrap($this->extrahelp, self::OUTPUTWIDTH));
        }

        $this->cli_writeln('');
        $this->cli_writeln('Example:');
        $needswwwuser = true;
        $params = '';
        $this->cli_writeln('$ ' . ($needswwwuser ? 'sudo -u www-data ' : '') .
            '/usr/bin/php admin/tool/vault/cli/'.$this->scriptfilename.$params);
    }

    /**
     * Validates this CLI options and overrides Vault settings
     *
     * @return void
     */
    public function validate_cli_options() {
        global $CFG;

        foreach ($this->options_definitions() as $key => $definition) {
            if ($validator = (isset($definition['validation']) ? $definition['validation'] : null)) {
                $validator($this->get_cli_option($key));
            }
        }

        // Add config overrides to the $CFG.
        $CFG->forced_plugin_settings = isset($CFG->forced_plugin_settings) ? $CFG->forced_plugin_settings : [];
        $CFG->forced_plugin_settings += ['tool_vault' => []];
        if ($this->get_cli_option('apikey')) {
            $CFG->forced_plugin_settings['tool_vault']['apikey'] = $this->get_cli_option('apikey');
        }
    }

    /**
     * Display available CLI options as a table
     *
     * @param array $options
     */
    protected function print_help_options(array $options) {
        $left = [];
        $right = [];
        foreach ($options as $key => $option) {
            if ($option['hasvalue'] !== false) {
                $l = "--$key={$option['hasvalue']}";
            } else if (!empty($option['alias'])) {
                $l = "-{$option['alias']}, --$key";
            } else {
                $l = "--$key";
            }
            $left[] = $l;
            $right[] = $option['description'];
        }
        $this->cli_write('Options:' . PHP_EOL . $this->convert_to_table($left, $right));
    }

    /**
     * Display as CLI table
     *
     * @param array $column1
     * @param array $column2
     * @param int $indent
     * @return string
     */
    protected function convert_to_table(array $column1, array $column2, $indent = 0) {
        $maxlengthleft = 0;
        $left = [];
        $column1 = array_values($column1);
        $column2 = array_values($column2);
        foreach ($column1 as $i => $l) {
            $left[$i] = str_repeat(' ', $indent) . $l;
            if (strlen('' . $column2[$i])) {
                $maxlengthleft = max($maxlengthleft, strlen($l) + $indent);
            }
        }
        $maxlengthright = self::OUTPUTWIDTH - $maxlengthleft - 1;
        $output = '';
        foreach ($column2 as $i => $r) {
            if (!strlen('' . $r)) {
                $output .= $left[$i] . "\n";
                continue;
            }
            $right = wordwrap($r, $maxlengthright, "\n");
            $output .= str_pad($left[$i], $maxlengthleft) . ' ' .
                str_replace("\n", PHP_EOL . str_repeat(' ', $maxlengthleft + 1), $right) . PHP_EOL;
        }
        return $output;
    }

    /**
     * Print assoc array (key=>value) as a table
     *
     * @param array $data
     * @return string
     */
    public function print_table(array $data) {
        return $this->convert_to_table(array_keys($data), array_values($data));
    }

    /**
     * Get CLI option
     *
     * @param string $key
     * @return mixed|null
     */
    public function get_cli_option($key) {
        return isset($this->clioptions[$key]) ? $this->clioptions[$key] : null;
    }

    /**
     * Write a text to the given stream
     *
     * @param string $text text to be written
     */
    protected function cli_write($text) {
        if (PHPUNIT_TEST) {
            echo $text;
        } else {
            cli_write($text);
        }
    }

    /**
     * Write a text followed by an end of line symbol to the given stream
     *
     * @param string $text text to be written
     */
    public function cli_writeln($text) {
        $this->cli_write($text . PHP_EOL);
    }

    /**
     * Write to standard error output and exit with the given code
     *
     * @param string $text
     * @param int $errorcode
     * @return void (does not return)
     */
    protected function cli_error($text, $errorcode = 1) {
        $this->cli_problem($text);
        $this->clidie($errorcode);
    }

    /**
     * Wrapper for "die()" method so we can unittest it
     *
     * @param mixed $errorcode
     * @throws \moodle_exception
     */
    protected function clidie($errorcode) {
        if (!PHPUNIT_TEST) {
            die($errorcode);
        } else {
            throw new \moodle_exception('CLI script finished with error code '.$errorcode);
        }
    }

    /**
     * Write error notification
     * @param string $text
     * @return void
     */
    protected function cli_problem($text) {
        if (PHPUNIT_TEST) {
            echo $text;
        } else {
            cli_problem($text);
        }
    }
}
