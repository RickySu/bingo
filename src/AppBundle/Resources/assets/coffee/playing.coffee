numberArray = [0..24]
socketBaseUrl = $('#socketio-baseurl').val()
apiBaseUrl = $('#api-baseurl').val()
playerId = parseInt($('#player-id').val())
currentPlayer =
    id: null
    name: '遊戲尚未開始'
isWaiting = () ->
    currentPlayer.id != playerId
syncWaiting = () ->
    $('#current-playername').html currentPlayer.name;
    if currentPlayer.id == playerId
        $('#numbers').removeClass 'waiting'
    else
        $('#numbers').addClass 'waiting'
    return
init = () ->
    syncWaiting()
    for number in $('#numbers >button')
        $(number).removeAttr 'disabled'
        value = parseInt($(number).find('*[data-number]').html())
        numberArray[value-1] = [$(number).attr('data-row'), $(number).attr('data-col')];
    return
play = () ->
    $('#numbers >button').click () ->
        return if isWaiting()
        number = parseInt $(@).find('*[data-number]').html()
        $.post "#{apiBaseUrl}/selectNumber", {number: number}, (result) ->
            return
        , 'JSON'
        return
numberSelected = (row, col) ->
    $("#numbers >button[data-row=#{row}][data-col=#{col}]").addClass 'btn-success'
    $("#numbers >button[data-row=#{row}][data-col=#{col}]").attr 'disabled', 'disabled'
    return
$ () ->
    init()
    play()
    playersocket = io.connect("#{socketBaseUrl}/player")
    playersocket.on 'connect', () ->
        return
    playersocket.on 'number', (data) ->
        currentPlayer.id = data.currentPlayerId
        currentPlayer.name = data.currentPlayerName
        syncWaiting()
        numberSelected numberArray[data.number-1][0], numberArray[data.number-1][1] if data.number
        return
    playersocket.on 'lines', (data) ->
        for line in data
            if line.playerId == playerId
                $('#my-lines').html line.lines
        return
    playersocket.on 'gameover', (winner) ->
        currentPlayer.id = null
        syncWaiting()
        for id in winner
            if id == playerId
                currentPlayer.name = "遊戲結束-獲勝"
                syncWaiting()
                alert "我贏了"
                return
            currentPlayer.name = "遊戲結束-輸了"
            syncWaiting()
            alert "我輸了"
        return
    $.getJSON "#{apiBaseUrl}/allNumbers", (data) ->
        return if not data.status
        currentPlayer.id = data.currentPlayerId
        currentPlayer.name = data.currentPlayerName
        syncWaiting()
        for number in data.numbers
            numberSelected numberArray[number-1][0], numberArray[number-1][1]
        return
    return