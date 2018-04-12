<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Config\Console\Command\ConfigSet;

use Magento\Config\Console\Command\ConfigSetCommand;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Scope\ValidatorInterface;
use Magento\Config\Model\Config\PathValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\ConfigurationMismatchException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Deploy\Model\DeploymentConfig\Hash;
use Magento\Config\App\Config\Type\System;
use Magento\Framework\App\Config;

/**
 * Processor facade for config:set command.
 *
 * @see ConfigSetCommand
 *
 * @api
 * @since 100.2.0
 */
class ProcessorFacade
{
    /**
     * The scope and scope code validator.
     *
     * Checks if scope and scope code exist, and scope code belongs to scope.
     * For example, scope "websites" and scope code "base" exist, and scope code "base" belongs to scope "website".
     *
     * @var ValidatorInterface
     */
    private $scopeValidator;

    /**
     * The path validator.
     *
     * Checks whether the config path present in configuration structure.
     *
     * @var PathValidator
     */
    private $pathValidator;

    /**
     * The factory for config:set processors.
     *
     * @var ConfigSetProcessorFactory
     */
    private $configSetProcessorFactory;

    /**
     * The hash manager.
     *
     * @var Hash
     */
    private $hash;

    /**
     * The application config storage.
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ValidatorInterface $scopeValidator The scope validator
     * @param PathValidator $pathValidator The path validator
     * @param ConfigSetProcessorFactory $configSetProcessorFactory The factory for config:set processors
     * @param Hash $hash The hash manager
     * @param ScopeConfigInterface $scopeConfig The application config storage
     */
    public function __construct(
        ValidatorInterface $scopeValidator,
        PathValidator $pathValidator,
        ConfigSetProcessorFactory $configSetProcessorFactory,
        Hash $hash,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeValidator = $scopeValidator;
        $this->pathValidator = $pathValidator;
        $this->configSetProcessorFactory = $configSetProcessorFactory;
        $this->hash = $hash;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Processes config:set command.
     *
     * @param string $path The configuration path in format section/group/field_name
     * @param string $value The configuration value
     * @param string $scope The configuration scope (default, website, or store)
     * @param string $scopeCode The scope code
     * @param boolean $lock The lock flag
     * @return string Processor response message
     * @throws ValidatorException If some validation is wrong
     * @throws CouldNotSaveException If cannot save config value
     * @throws ConfigurationMismatchException If processor can not be instantiated
     * @since 100.2.0
     */
    public function process($path, $value, $scope, $scopeCode, $lock)
    {
        try {
            $this->scopeValidator->isValid($scope, $scopeCode);
            $this->pathValidator->validate($path);
        } catch (LocalizedException $exception) {
            throw new ValidatorException(__($exception->getMessage()), $exception);
        }

        $processor = $lock
            ? $this->configSetProcessorFactory->create(ConfigSetProcessorFactory::TYPE_LOCK)
            : $this->configSetProcessorFactory->create(ConfigSetProcessorFactory::TYPE_DEFAULT);
        $message = $lock
            ? 'Value was saved and locked.'
            : 'Value was saved.';

        // The processing flow depends on --lock option.
        $processor->process($path, $value, $scope, $scopeCode);

        $this->hash->regenerate(System::CONFIG_TYPE);

        if ($this->scopeConfig instanceof Config) {
            $this->scopeConfig->clean();
        }

        return $message;
    }
}
