document.addEventListener('DOMContentLoaded', function() {

    if(document.getElementById('nomenclator').value == 1) {
        let ajaxRoute = document.getElementById('ajaxRoute').value;
        let token = document.getElementById('token').value;

        let cityInput = document.querySelector("input[name=\'city\']");
        if (cityInput) {
            let select = document.createElement('select');
            select.name = cityInput.name;
            select.id = cityInput.id;
            select.classList.add('form-control');

            let cities = ['Alege un judet'];
            cities.forEach(function(city) {
                let option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                select.appendChild(option);
            });
            cityInput.parentNode.replaceChild(select, cityInput);
        }

        let countyElement = document.querySelector("[name='id_state']");
        if(countyElement){
            countyElement.addEventListener("change", function () {
                let county = countyElement.value;
                $.ajax({
                    type: "POST",
                    url: ajaxRoute,
                    data: {
                        action: 'CitiesAjax',
                        county_id: county,
                        token: token
                    },
                    success: function (response) {
                        let cityInput = document.getElementById('field-city');
                        let html = '';
                        response.forEach((item) => html += '<option value="' + item['city_name'] + '">' + item['city_name'] + '</option>');
                        cityInput.innerHTML = html;
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', error);
                    }
                });

            });
        }

    }

});