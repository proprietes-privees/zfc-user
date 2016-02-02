<?php

namespace ZfcUser\Entity;

interface UserInterface
{
    /**
     * Get id.
     *
     * @return int
     */
    public function getId();

    /**
     * Set id.
     *
     * @param int $id
     * @return UserInterface
     */
    public function setId($id);

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode();

    /**
     * Set code.
     *
     * @param string $code
     * @return UserInterface
     */
    public function setCode($code);

    /**
     * Get password.
     *
     * @return string password
     */
    public function getPassword();

    /**
     * Set password.
     *
     * @param string $password
     * @return UserInterface
     */
    public function setPassword($password);

    /**
     * Get active.
     *
     * @return int
     */
    public function getActive();

    /**
     * Set active.
     *
     * @param int $active
     * @return UserInterface
     */
    public function setActive($active);

	/**
     * Get displayName.
     *
     * @return string
     */
    public function getDisplayName();

    /**
     * Set displayName.
     *
     * @param string $displayName
     * @return UserInterface
     */
    public function setDisplayName($displayName);
}
