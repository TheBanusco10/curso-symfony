function meGusta(id) {

    const ruta = Routing.generate('likes');

    $.ajax({
        type: 'POST',
        url: ruta,
        data: ({id: id}),
        async: true,
        dataType: 'json',
        success: function (data) {
            console.log(data.likes);
        }
    });
}