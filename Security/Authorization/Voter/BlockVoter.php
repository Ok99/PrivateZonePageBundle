<?php

namespace Ok99\PrivateZoneCore\PageBundle\Security\Authorization\Voter;

use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class BlockVoter implements VoterInterface
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function supportsAttribute($attribute)
    {
        return $attribute === 'EDIT' || $attribute === 'DELETE';
    }

    public function supportsClass($class)
    {
        $supportedClass = 'Ok99\PrivateZoneCore\PageBundle\Entity\Block';

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param TokenInterface $token
     * @param \Ok99\PrivateZoneCore\PageBundle\Entity\Block $block
     * @param array $attributes
     * @return int
     */
    public function vote(TokenInterface $token, $block, array $attributes)
    {
        if (!$block || !$this->supportsClass(get_class($block))) {
            return self::ACCESS_ABSTAIN;
        }

        $securityContext = $this->container->get('security.context');

        // check parents
        $parent = $block->getParent();
        while ($parent) {
            if ($securityContext->isGranted($attributes, $parent)) {
                return self::ACCESS_GRANTED;
            }

            $parent = $parent->getParent();
        }

        return self::ACCESS_DENIED;
    }
}
