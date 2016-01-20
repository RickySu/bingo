numberArray = null

socketBaseUrl = $('#socketio-baseurl').val()
apiBaseUrl = $('#api-baseurl').val()

makeArray = () ->
    numberArray = [[0..4], [0..4], [0..4], [0..4], [0..4]]
    return

getUserName = () ->
    if $('#username-modal').length
        $('#username-modal').modal
            backdrop: 'static'
            keyboard: false
    $('#username-modal form').submit (e) ->
        e.preventDefault()
        e.stopPropagation()
        $.post "#{apiBaseUrl}/register", {name: $('#name').val()}, (result) ->
            location.reload()
            return
        return
    return

initNumbers = () ->
    $('#start').hide()
    currentNumber = 1
    $('#numbers *[data-number]').html ''
    $('#numbers >button').unbind('click')
    $('#numbers >button').click () ->
        if $(@).find('*[data-number]').html() == ''
            numberArray[parseInt($(@).attr 'data-row')][parseInt($(@).attr 'data-col')] = currentNumber
            $(@).find('*[data-number]').html currentNumber++
            if currentNumber > 25
                $('#start').fadeIn()
        return

autoselectNumbers = () ->
    shuffle = (array) ->
        for i in [0..array.length]
            n1 = Math.floor(Math.random()*array.length)
            n2 = Math.floor(Math.random()*array.length)
            tmp = array[n1]
            array[n1] = array[n2]
            array[n2] = tmp
        array
    $('#autoselect').click () ->
        initNumbers()
        array = shuffle([0..24])
        for i in array
            row = Math.floor(i/5)
            col = i % 5
            $("button[data-row=#{row}][data-col=#{col}]").trigger 'click'
        return
    return
startGame = () ->
    $('#start').click () ->
        $.post "#{apiBaseUrl}/playerStart", {numbers: numberArray}, (result) ->
            if result.status
                window.location = "#{apiBaseUrl}/playing"
            return
        , 'JSON'
        return
    return
$ () ->
    makeArray()
    getUserName()
    initNumbers()
    autoselectNumbers()
    startGame()
    $('#resetNumber').click () ->
        initNumbers()
        return
    playersocket = io.connect("#{socketBaseUrl}/player")
    playersocket.on 'connect', () ->
        return
    return