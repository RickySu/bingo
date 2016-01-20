<?php

namespace AppBundle\Model;

use AppBundle\Model\om\BaseGame;

class Game extends BaseGame
{
    public function init()
    {
        $players = $this->getPlayers();
        $nPlayers = count($players);
        if($nPlayers == 0){
            return false;
        }
        $orders = range(0, $nPlayers - 1);
        shuffle($orders);
        foreach($players as $index => $player){
            $player->setSort($orders[$index]);
            $player->save();
        }
        $this->setStatus(GamePeer::STATUS_GAMING);
        $this->save();
        return true;
    }

    public function getSortedPlayers()
    {
        $query = PlayerQuery::create()->orderBySort();
        return $this->getPlayers($query);
    }

    /**
     * @return Player
     * @throws \PropelException
     */
    public function getCurrentPlayer()
    {
        $round = RoundQuery::create()
            ->filterByGame($this)
            ->orderById(\Criteria::DESC)
            ->findOne();
        if(!$round){
            return PlayerQuery::create()
                ->filterByGame($this)
                ->orderBySort()
                ->findOne();
        }
        $nPlayers = $this->countPlayers();
        $sort = ($round->getPlayer()->getSort() + 1) % $nPlayers;
        return PlayerQuery::create()
            ->filterByGame($this)
            ->filterBySort($sort)
            ->findOne();
    }

    public function getNumbers()
    {
        $numbers = array();

        foreach($this->getRounds() as $round){
            $numbers[] = $round->getNumber();
        }

        return $numbers;
    }
}
