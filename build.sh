#!/bin/sh

cd ..
zip -r ./samedaycourier/samedaycourier.zip ./samedaycourier
cd samedaycourier
zip --delete samedaycourier.zip "samedaycourier/.git*"
