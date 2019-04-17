#!/bin/sh

zip -r samedaycourier.zip ./
zip --delete samedaycourier.zip ".git*"
