#!/bin/sh

rm -rf samedaycourier
mkdir samedaycourier
cp -r ./ ./samedaycourier/
rm -rf samedaycourier/samedaycourier
zip -r samedaycourier.zip ./samedaycourier
zip --delete samedaycourier.zip "samedaycourier/.git*"
zip --delete samedaycourier.zip "samedaycourier/config.xml"
zip --delete samedaycourier.zip "samedaycourier/log/*.log"
rm -rf samedaycourier
