/**
 * Constants for field types
 */
const FIELD_TYPE = 'field';

$(document).ready(() => {
    let citySelectElement;

    let formElements = {
        country: $(getFieldByType('country', FIELD_TYPE)),
        state: $(getFieldByType('state', FIELD_TYPE)),
        city: $(getFieldByType('city', FIELD_TYPE)),
    };

    if (undefined !== formElements.state && formElements.state.length > 0) {
        formElements.state.on('change', (event) => {
            updateCities(formElements.city[0], event.target.value, formElements.country.val());
        });
    }

    const updateCities = (cityField, stateCode, countryCode) => {
        let cities = SamedayCities[countryCode][stateCode] ?? [];
        if (cities.length > 0) {
            if (undefined !== citySelectElement && citySelectElement.length > 0) {
                populateCityField(cities, citySelectElement, cityField);
            } else {
                citySelectElement = document.createElement("select");
                citySelectElement.setAttribute("id", cityField.getAttribute('id'));
                citySelectElement.setAttribute("name", 'city');
                citySelectElement.setAttribute("class", "form-control form-control-select");

                populateCityField(cities, citySelectElement, cityField);
            }
        } else {
            if (undefined !== citySelectElement && citySelectElement.length > 0) {
                citySelectElement.replaceWith(cityField);
            }
        }
    }

    const createOptionElement = (value, text, cityFieldValue = null) => {
        const option = document.createElement('option');
        option.value = value;
        option.setAttribute('data-alternate-values', `[${value}]`);
        if (value === cityFieldValue) {
            option.setAttribute('selected', true);
        }
        option.textContent = text;

        return option;
    }

    const populateCityField = (cities, citySelectElement, cityField) => {
        citySelectElement.textContent = "";
        citySelectElement.appendChild(createOptionElement("", "Choose a city"));
        cities.forEach((city) => {
            citySelectElement.appendChild(createOptionElement(city.name, city.name, cityField.value));
        });

        cityField.replaceWith(citySelectElement);
    }
});

/**
 * @param fieldName
 * @param type
 *
 * @returns HTML|undefined
 */
const getFieldByType = (fieldName, type) => {
    return Array.from(document.querySelectorAll(`input[id*=${type}], select[id*=${type}]`))
        .find(element => element.id.includes(fieldName)
    );
}

if (typeof $.migrateMute !== "undefined") {
    $.migrateMute = true; // Dezactivează complet mesajele JQMigrate în consolă.
}

