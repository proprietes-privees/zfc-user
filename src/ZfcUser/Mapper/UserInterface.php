<?php

namespace ZfcUser\Mapper;

interface UserInterface
{
    public function findByEmail($email);

    public function findByCode($code);

    public function findById($id);

    public function insert($user);

    public function update($user);
}
