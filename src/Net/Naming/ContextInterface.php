<?php

namespace Util\Net\Naming;

interface ContextInterface
{
    public const INITIAL_CONTEXT_FACTORY = "naming.factory.initial";

    public const OBJECT_FACTORIES = "naming.factory.object";

    public const STATE_FACTORIES = "naming.factory.state";

    public const URL_PKG_PREFIXES = "Util\\Net\\Ndi\\Url";

    public const PROVIDER_URL = "naming.provider.url";

    public const DNS_URL = "naming.dns.url";

    public const AUTHORITATIVE = "naming.authoritative";

    public const BATCHSIZE = "naming.batchsize";

    public const REFERRAL = "naming.referral";

    public const SECURITY_PROTOCOL = "naming.security.protocol";

    public const SECURITY_AUTHENTICATION = "naming.security.authentication";

    public const SECURITY_PRINCIPAL = "naming.security.principal";

    public const SECURITY_CREDENTIALS = "naming.security.credentials";

    public const LANGUAGE = "naming.language";
}
