<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Model;

class DefaultController extends Controller
{
    /**
     * @Route("/game")
     * @Template()
     */
    public function gameAction(Request $request)
    {
        $session = $this->get('session');
        $game = $session->get('game', new Model\Game());
        if($game->isNew()){
            $game->save();
            $session->set('game', $game);
        }
        try {
            $game->reload(true);
            if($game->getStatus() == Model\GamePeer::STATUS_FINISH){
                $session->remove('game');
                return $this->redirectToRoute("app_default_game");
            }
        }catch (\PropelException $e){
            $session->remove('game');
            return $this->redirectToRoute("app_default_game");
        }
        return array('game' => $game);
    }

    /**
     * @Route("/{id}/gamestart")
     */
    public function gameStartAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() != Model\GamePeer::STATUS_PREPARE){
            throw $this->createNotFoundException();
        }
        return new JsonResponse(array(
            'status' => $game->init(),
            'redirect' => $this->generateUrl('app_default_gaming', array('id' => $game->getId())),
        ));
    }

    /**
     * @Route("/{id}/gaming")
     * @Template()
     */
    public function gamingAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_PREPARE){
            throw $this->createNotFoundException();
        }
        $currentPlayer = $game->getCurrentPlayer();
        $this->emit('player', 'number', array(
            'number' => null,
            'playerId' => null,
            'currentPlayerId' => $currentPlayer->getId(),
            'currentPlayerName' => $currentPlayer->getName(),
        ));
        return array('game' => $game, 'currentPlayer' => $currentPlayer);
    }

    /**
     * @Route("/{id}/test")
     */
    public function testAction(Request $request, Model\Game $game)
    {
        $player = Model\PlayerQuery::create()->findPk(7);
        $this->gameover($game, array(1));
        die;
    }

    protected function emit($channel, $event, $message = null)
    {
        $client = new \Guzzle\Http\Client();
        $apiUrl = "{$this->container->getParameter('socketio_baseurl')}/{$channel}/emit";
        $request = $client->post($apiUrl, array('Content-Type' => 'application/json'), json_encode(array(
            'event' => $event,
            'message' => $message,
        )));
        $client->send($request);
    }

    /**
     * @Route("/{id}/play")
     * @Template()
     */
    public function playAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_FINISH){
            throw $this->createNotFoundException();
        }

        $session = $this->get('session');
        $player = $session->get('player');
        if($player){
            try {
                $player->reload(true);
                $session->set('player', $player);
            }catch (\PropelException $e){
                $session->remove('player');
                return $this->redirectToRoute("app_default_play", array('id' => $game->getId()));
            }
        }
        return array('game'=> $game, 'player' => $player);
    }

    /**
     * @Route("/{id}/syncPlayers")
     * @Template()
     */
    public function syncPlayersAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_FINISH){
            throw $this->createNotFoundException();
        }
        $this->syncPlayers($game);
        return new JsonResponse(array(
            'status' => true,
        ));
    }

    protected function syncPlayers(Model\Game $game)
    {
        $query = Model\PlayerQuery::create()
            ->orderBySort();
        $players = array();
        foreach($game->getPlayers($query) as $player){
            $players[] = array(
                'id' => $player->getId(),
                'name' => $player->getName(),
                'lines' => $player->getLines(),
                'ready' => $player->getNumbers() != null,
            );
        }
        $this->emit('game', 'players', $players);
    }

    /**
     * @Route("/{id}/register")
     */
    public function registerNameAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_FINISH){
            throw $this->createNotFoundException();
        }
        $name = $request->get('name');
        if(trim($name) == ''){
            return new JsonResponse(array(
                'status' => false,
            ));
        }

        $player = new Model\Player();
        $player->setName($name);
        $player->setGame($game);
        $player->save();
        $session = $this->get('session');
        $session->set('player', $player);
        $this->syncPlayers($game);
        return new JsonResponse(array(
            'status' => false,
        ));
    }


    /**
     * @Route("/{id}/playing")
     * @Template()
     */
    public function playingAction(Request $request, Model\Game $game)
    {
        $session = $this->get('session');
        $player = $session->get('player');

        if($game->getStatus() == Model\GamePeer::STATUS_FINISH || !$player){
            throw $this->createNotFoundException();
        }

        $player->reload(true);

        return array(
            'game' => $game,
            'player' => $player,
        );
    }

    /**
     * @Route("/{id}/playerStart")
     */
    public function playerStartAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_FINISH){
            throw $this->createNotFoundException();
        }

        $session = $this->get('session');
        $player = $session->get('player');

        if(!$player){
            return new JsonResponse(array(
                'status' => false,
            ));
        }

        $player->reload(true);

        $numbers = $request->request->get('numbers');
        if(!$this->checknumbers($numbers)){
            return new JsonResponse(array(
                'status' => false,
            ));

        }

        $player->setNumbers($numbers);
        $player->save();
        $this->syncPlayers($game);
        return new JsonResponse(array(
            'status' => true,
        ));
    }

    protected function checknumbers($numbers)
    {
        $value = [];
        for($i=0; $i<5; $i++){
            if(!isset($numbers[$i]) || !is_array($numbers[$i])){
                return false;
            }
            for($j=0; $j<5; $j++){
                if(!isset($numbers[$i][$j]) || !is_integer((int)$numbers[$i][$j])){
                    return false;
                }
                $value[] = (int)$numbers[$i][$j];
            }
        }
        $value = array_unique($value);
        sort($value);
        if(count($value) != 25 || $value[0] !=1 || $value[24] != 25){
            return false;
        }
        return true;
    }

    /**
     * @Route("/{id}/selectNumber")
     */
    public function selectNumberAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_FINISH){
            throw $this->createNotFoundException();
        }

        $session = $this->get('session');
        $player = $session->get('player');

        if(!$player){
            return new JsonResponse(array(
                'status' => false,
            ));
        }

        $player->reload(true);
        $number = (int) $request->request->get('number');

        if($number<=0 || $number>25){
            return new JsonResponse(array(
                'status' => false,
            ));
        }

        $this->selectNumber($player, $number);

        return new JsonResponse(array(
            'status' => true,
        ));
    }

    protected function gameover(Model\Game $game, $winner)
    {
        $game->setStatus(Model\GamePeer::STATUS_FINISH);
        $game->save();
        $this->emit('player', 'gameover', $winner);
    }

    protected function calculateAllPlayerLines(Model\Game $game, $number)
    {
        $winner = array();
        $lines = array();
        foreach($game->getSortedPlayers() as $player){
            $this->syncLines($player, $number);
            $lines[] = array(
                'playerId' => $player->getId(),
                'lines' => $player->getLines(),
            );
            if($player->getLines() >= 5){
                $winner[] = $player->getId();
            }
        }
        $this->emit('player', 'lines', $lines);
        if(count($winner) > 0){
            $this->gameover($game, $winner);
        }
    }

    /**
     * @Route("/{id}/autoSelectPlayerNumber")
     */
    public function autoSelectPlayerNumberAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_FINISH){
            throw $this->createNotFoundException();
        }

        $player = Model\PlayerQuery::create()->findPk($request->request->get('playerId'));

        if(!$player || $player->getGameId() != $game->getId()){
            throw $this->createNotFoundException();
        }

        $numbers = array_combine(range(1, 25), range(1, 25));

        foreach( $game->getRounds() as $round){
            unset($numbers[$round->getNumber()]);
        }

        $numbers = array_keys($numbers);
        if(count($numbers) > 0) {
            shuffle($numbers);
            $this->selectNumber($player, $numbers[0]);
        }
        return new JsonResponse(array(
            'status' => true,
        ));
    }

    protected function selectNumber(Model\Player $player, $number)
    {
        $game = $player->getGame();
        $round = new Model\Round();
        $round->setGame($game);
        $round->setPlayer($player);
        $round->setNumber($number);
        $round->save();
        $currentPlayer = $game->getCurrentPlayer();

        $this->emit('player', 'number', array(
            'number' => $number,
            'playerId' => $player->getId(),
            'currentPlayerId' => $currentPlayer->getId(),
            'currentPlayerName' => $currentPlayer->getName(),
        ));

        $this->calculateAllPlayerLines($game, $number);
    }

    /**
     * @Route("/{id}/allNumbers")
     */
    public function allNumbersAction(Request $request, Model\Game $game)
    {
        if($game->getStatus() == Model\GamePeer::STATUS_PREPARE){
            throw $this->createNotFoundException();
        }

        $currentPlayer = $game->getCurrentPlayer();

        return new JsonResponse(array(
            'status' => true,
            'currentPlayerId' => $currentPlayer->getId(),
            'currentPlayerName' => $currentPlayer->getName(),
            'numbers' => $game->getNumbers(),
        ));
    }

    protected function syncLines(Model\Player $player, $number)
    {
        $game = $player->getGame();
        $selectedNumbers = $game->getNumbers();
        $playerNumbers = $player->getNumbers();
        $pos = array();
        $lines = $player->getLines();

        for($row = 0; $row < 5; $row++){
            for($col = 0; $col < 5 ; $col++){
                if($playerNumbers[$row][$col] == $number){
                    $pos = array($row, $col);
                    break;
                }
            }
        }
        list($row, $col) = $pos;

        //search row
        $matched = 0;
        for($i=0; $i<5; $i++){
            if(in_array($playerNumbers[$row][$i], $selectedNumbers)){
                $matched++;
            }
        }
        if($matched==5){
            $lines++;
        }

        //search col
        $matched = 0;
        for($i=0; $i<5; $i++){
            if(in_array($playerNumbers[$i][$col], $selectedNumbers)){
                $matched++;
            }
        }
        if($matched==5){
            $lines++;
        }

        //search cross
        if($row == $col) {

            $matched = 0;
            for ($i = 0; $i < 5; $i++) {
                if (in_array($playerNumbers[$i][$i], $selectedNumbers)) {
                    $matched++;
                }
            }
            if ($matched == 5) {
                $lines++;
            }
        }

        //search cross
        if($row+$col == 4){
            $matched = 0;
            for($i=0; $i<5; $i++){
                if(in_array($playerNumbers[$i][4 - $i], $selectedNumbers)){
                    $matched++;
                }
            }
            if($matched==5){
                $lines++;
            }
            echo "/$lines";
        }

        $player->setLines($lines);
        $player->save();
    }
}
