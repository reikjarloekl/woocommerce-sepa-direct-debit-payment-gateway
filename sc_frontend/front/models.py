from django.db import models
from django.contrib.auth.models import User

class EmailAddress(models.Model):
    user = models.ForeignKey(User, null=True)
    name = models.CharField(max_length=60, default="")
    address = models.EmailField(max_length=254)
    verified = models.BooleanField(default=False)
    updated_at = models.DateTimeField(auto_now=True)

    def __str__(self):
        return "{}: {} (verified: {})".format(self.user.username, self.address, self.verified)


class Camera(models.Model):
    user = models.ForeignKey(User)
    name = models.CharField(max_length=100)
    email_addresses = models.ManyToManyField(EmailAddress)

    def __str__(self):
        return self.user.username + ': ' + self.name;


class Image(models.Model):
    camera = models.ForeignKey(Camera)
    received = models.DateTimeField()

