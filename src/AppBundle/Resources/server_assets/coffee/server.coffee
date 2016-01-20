channel =
    player: null
    game: null

handleRequest = (req, callback) ->
    data = ''
    req.on 'data', (dataPiece) ->
        data = "#{data}#{dataPiece}"
        return
    req.on 'end', () ->
        reqData = JSON.parse data
        callback reqData
        return
    return

webappHandler = (req, resp) ->
    if req.method != "POST"
        resp.end JSON.stringify
            status: false
        return
    if req.url == '/game/emit'
        handleRequest req, (data) ->
            channel.game.emit data.event, data.message
            return
    if req.url == '/player/emit'
        handleRequest req, (data) ->
            channel.player.emit data.event, data.message
            return
    resp.end JSON.stringify
        status: true
    return

app = require('http').createServer webappHandler
io = require('socket.io') app

channel.player = io
    .of '/player'
    .on 'connection' ,
    (socket) ->
        return

channel.game = io
    .of '/game'
    .on 'connection' ,
    (socket) ->
        return

app.listen 8080
