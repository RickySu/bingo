socketBaseUrl = $('#socketio-baseurl').val()
apiBaseUrl = $('#api-baseurl').val()

updatePlayers = (players) ->
    $('#waiting-players >*').empty()
    for player in players
        if player.ready
            $('#waiting-players >*').append "<span class='label label-primary'>#{player.name}</span> "
        else
            $('#waiting-players >*').append "<span class='label label-default'>#{player.name}</span> "
    return

autoSelectPlayerNumber = (playerId) ->
    $.post "#{apiBaseUrl}/autoSelectPlayerNumber", {playerId: playerId}, () ->
        return
    return

countDownHook = (playerId) ->
    window.setTimeout () ->
        if not $("*[data-player=#{playerId}]").hasClass('panel-primary')
            return
        progress = parseInt $("*[data-player=#{playerId}] .progress-bar").attr 'aria-valuenow'
        progress = progress - 1
        if progress <= 0
            autoSelectPlayerNumber playerId
            return
        percent = Math.floor(progress*100 / 300)
        $("*[data-player=#{playerId}] .progress-bar").attr 'aria-valuenow', progress
        $("*[data-player=#{playerId}] .progress-bar").css 'width', "#{percent}%"
        countDownHook playerId
        return
    , 100
    return

startCountDown = () ->
    return if $('#gaming .panel-primary').length == 0
    countDownHook $('#gaming .panel-primary').attr 'data-player'
    return

updateNumber = (data) ->
    $("*[data-player=#{data.playerId}]").find("*[data-numbers]").append "<span class='label label-info'>#{data.number}</span> "
    return

updateRound = (data) ->
    $("*[data-player]").removeClass 'panel-primary'
    $("*[data-player=#{data.currentPlayerId}]").addClass 'panel-primary'
    $("*[data-player=#{data.currentPlayerId}] .progress-bar").attr 'aria-valuenow', 300
    startCountDown()
    return

$ () ->
    startCountDown()
    $('#gamestart').click (e) ->
        e.preventDefault()
        e.stopPropagation()
        $.post "#{apiBaseUrl}/gamestart", (result) ->
            window.location = result.redirect if result.status
            return
        , 'JSON'
        return
    gamesocket = io.connect("#{socketBaseUrl}/game")
    gamesocket.on 'connect', () ->
        $.get "#{apiBaseUrl}/syncPlayers", () ->
            return
        return
    gamesocket.on 'players', (players) ->
        updatePlayers(players)
        return
    playersocket = io.connect("#{socketBaseUrl}/player")
    playersocket.on 'connect', () ->
        return
    playersocket.on 'number', (data) ->
        updateNumber data if data.playerId
        updateRound data
        return
    playersocket.on 'lines', (data) ->
        for line in data
            $("*[data-player=#{line.playerId}] *[data-line]").html line.lines
        return
    playersocket.on 'gameover', (winner) ->
        $("*[data-player]").removeClass 'panel-primary'
        for id in winner
            $("*[data-player=#{id}]").addClass 'panel-danger'
        return
    return